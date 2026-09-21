<?php

namespace App\Services;

use App\Events\OrderStatusChanged;
use App\Models\Order;
use App\Models\OrderDelivery;
use App\Models\OrderStatusHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Single source of truth for the order lifecycle.
 *
 * Every real status change in KDP MART goes through transition(): controllers
 * and other services never write `$order->status` themselves. The service:
 *
 *  1. validates the target status exists,
 *  2. re-reads the CURRENT status inside a transaction (double-click safe),
 *  3. enforces the lifecycle rules (one step forward, cancel from pending /
 *     confirmed only — see Order::ALLOWED_TRANSITIONS),
 *  4. enforces the actor's role (buyer / seller / delivery partner / admin),
 *  5. records the lifecycle timestamp,
 *  6. writes exactly one audit-trail row (order_status_histories),
 *  7. dispatches OrderStatusChanged so the future notification/inventory
 *     phases can hook in without touching this code.
 *
 * Failures are explicit: an invalid lifecycle move throws a
 * ValidationException carrying a human explanation ("Order cannot be marked
 * as Delivered because it has not been picked up yet."), while a role /
 * ownership violation aborts with 403 — the UI is never trusted.
 */
class OrderStatusService
{
    /**
     * The transitions each role may perform, keyed by role then by the
     * status the order must be in.
     *
     * Admins are not listed: they may perform any transition the lifecycle
     * allows. Nobody may skip steps.
     *
     * @var array<string, array<string, array<int, string>>>
     */
    private const ROLE_PERMISSIONS = [
        'buyer' => [
            'pending'   => ['cancelled'],
            'confirmed' => ['cancelled'],
        ],
        'seller' => [
            'confirmed'  => ['processing'],
            'processing' => ['ready_for_pickup'],
        ],
        'delivery_partner' => [
            'assigned'         => ['picked_up'],
            'picked_up'        => ['out_for_delivery'],
            'out_for_delivery' => ['delivered'],
        ],
    ];

    /**
     * Delivery status => the ORDER status it implies. Used by
     * syncWithDelivery() so the two layers can never drift apart.
     */
    private const DELIVERY_STATUS_MAP = [
        'assigned'         => 'assigned',
        'ready_for_pickup' => 'ready_for_pickup',
        'picked_up'        => 'picked_up',
        'out_for_delivery' => 'out_for_delivery',
        'delivered'        => 'delivered',
    ];

    /**
     * Controlled status change.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function transition(Order $order, string $newStatus, ?User $actor = null, ?string $note = null): OrderStatusHistory
    {
        $this->assertKnownStatus($newStatus);

        $this->assertRoleMayTransition($order, $newStatus, $actor);

        return DB::transaction(function () use ($order, $newStatus, $actor, $note): OrderStatusHistory {
            // Re-read the current state under a row lock: a second click on
            // the same button can never create a second history entry, move
            // the order twice or re-run side effects.
            $fresh = Order::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();

            $fromStatus = $fresh->status;

            if ($newStatus === $fromStatus) {
                throw ValidationException::withMessages([
                    'status' => 'Order ' . $fresh->order_number . ' is already marked as '
                        . $fresh->statusLabel() . '.',
                ]);
            }

            $this->assertLifecycleAllows($fresh, $newStatus);

            $history = $this->applyTransition($fresh, $fromStatus, $newStatus, $actor, $note);

            // Keep the caller's instance in sync (controllers render it).
            $order->setRawAttributes($fresh->getAttributes(), true);

            return $history;
        });
    }

    /**
     * Delivery-authoritative sync.
     *
     * The delivery layer validates its own lifecycle (OrderDelivery::
     * ALLOWED_TRANSITIONS) and is the authority for the delivery-owned order
     * statuses. This method mirrors an already-validated delivery state onto
     * the order — forward only, never out of a terminal status — and records
     * the same timestamp + audit entry as a normal transition.
     *
     * It is the ONLY place allowed to move an order outside the strict
     * one-step-forward chain, because the delivery layer already validated the
     * step. It also keeps orders that were assigned before "ready for pickup"
     * became mandatory (pre-existing data) working end to end.
     */
    public function syncWithDelivery(
        Order $order,
        OrderDelivery $delivery,
        ?User $actor = null,
        ?string $note = null,
    ): ?OrderStatusHistory {
        $target = self::DELIVERY_STATUS_MAP[$delivery->status] ?? null;

        if ($target === null || $order->status === $target) {
            return null;
        }

        return DB::transaction(function () use ($order, $target, $actor, $note): ?OrderStatusHistory {
            $fresh = Order::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();

            // Never reopen a finished order and never move backwards.
            if ($fresh->status === $target
                || in_array($fresh->status, ['delivered', 'cancelled'], true)
                || Order::stepIndex($target) <= Order::stepIndex($fresh->status)) {
                return null;
            }

            $history = $this->applyTransition($fresh, $fresh->status, $target, $actor, $note);

            $order->setRawAttributes($fresh->getAttributes(), true);

            return $history;
        });
    }

    /**
     * Whether the actor may perform the transition, with the reason when they
     * may not (used by the UI to explain unavailable actions — the same rules
     * are re-checked here server-side).
     *
     * @return array{allowed: bool, reason: string}
     */
    public function permissions(Order $order, string $newStatus, ?User $actor): array
    {
        if (! in_array($newStatus, Order::STATUSES, true)) {
            return ['allowed' => false, 'reason' => 'That is not a valid order status.'];
        }

        if ($newStatus === $order->status) {
            return ['allowed' => false, 'reason' => 'The order is already ' . $order->statusLabel() . '.'];
        }

        if (! $order->canTransitionTo($newStatus)) {
            return ['allowed' => false, 'reason' => $this->lifecycleReason($order, $newStatus)];
        }

        $role = $actor ? $this->roleOf($actor) : 'system';

        if ($role !== 'system' && $role !== 'admin' && ! $this->roleAllows($role, $order->status, $newStatus)) {
            return ['allowed' => false, 'reason' => $this->roleReason($role, $newStatus)];
        }

        if ($role === 'buyer' && (int) $order->user_id !== (int) $actor->id) {
            return ['allowed' => false, 'reason' => 'You can only act on your own orders.'];
        }

        if ($role === 'seller' && ! $this->sellerOwnsAnyLine($order, $actor)) {
            return ['allowed' => false, 'reason' => 'You can only act on orders that contain your products.'];
        }

        if ($role === 'delivery_partner' && ! $this->partnerOwnsOrder($order, $actor)) {
            return ['allowed' => false, 'reason' => 'You can only act on orders assigned to you.'];
        }

        return ['allowed' => true, 'reason' => ''];
    }

    /**
     * Whether the actor may transition the order (boolean shortcut).
     */
    public function canTransition(Order $order, string $newStatus, ?User $actor): bool
    {
        return $this->permissions($order, $newStatus, $actor)['allowed'];
    }

    /**
     * Human explanation for a lifecycle violation.
     */
    public function lifecycleReason(Order $order, string $newStatus): string
    {
        $target = Order::STATUS_LABELS[$newStatus] ?? ucfirst($newStatus);
        $required = Order::STATUS_PRECONDITIONS[$newStatus] ?? null;

        // The order is still BEFORE the required step: tell the user exactly
        // what is missing ("Order cannot be marked as Delivered because it
        // has not been picked up yet.").
        if ($required !== null
            && ! in_array($required, ['pending', 'confirmed'], true)
            && Order::stepIndex($order->status) < Order::stepIndex($required)) {
            return 'Order cannot be marked as ' . $target . ' because it has not been '
                . $this->actionPhrase($required) . ' yet.';
        }

        return 'Order ' . $order->order_number . ' cannot be moved from '
            . $order->statusLabel() . ' to ' . $target . '.';
    }

    /**
     * Perform the DB write + audit row + event for one real transition.
     */
    private function applyTransition(
        Order $order,
        string $fromStatus,
        string $toStatus,
        ?User $actor,
        ?string $note,
    ): OrderStatusHistory {
        $attributes = ['status' => $toStatus];

        $timestampColumn = Order::timestampColumnFor($toStatus);

        if ($timestampColumn !== null) {
            // Laravel's own date handling — never raw string manipulation.
            $attributes[$timestampColumn] = now();
        }

        $order->forceFill($attributes)->save();

        $history = $order->statusHistories()->create([
            'order_id'    => $order->getKey(),
            'from_status' => $fromStatus,
            'to_status'   => $toStatus,
            'changed_by'  => $actor?->id,
            'note'        => $note,
        ]);

        OrderStatusChanged::dispatch($order, $history, $actor, $fromStatus, $toStatus);

        return $history;
    }

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    private function assertKnownStatus(string $status): void
    {
        if (! in_array($status, Order::STATUSES, true)) {
            throw ValidationException::withMessages([
                'status' => 'That is not a valid order status.',
            ]);
        }
    }

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    private function assertLifecycleAllows(Order $order, string $newStatus): void
    {
        if (! $order->canTransitionTo($newStatus)) {
            throw ValidationException::withMessages([
                'status' => $this->lifecycleReason($order, $newStatus),
            ]);
        }
    }

    /**
     * Role + ownership enforcement. Violations are authorization failures
     * (403), not validation errors: the request was never allowed to exist.
     */
    private function assertRoleMayTransition(Order $order, string $newStatus, ?User $actor): void
    {
        $role = $actor ? $this->roleOf($actor) : 'system';

        if ($role === 'system' || $role === 'admin') {
            return;
        }

        if ($role === 'buyer' && (int) $order->user_id !== (int) $actor->id) {
            abort(403, 'You can only update your own orders.');
        }

        if ($role === 'seller' && ! $this->sellerOwnsAnyLine($order, $actor)) {
            abort(403, 'You can only update orders that contain your products.');
        }

        if ($role === 'delivery_partner' && ! $this->partnerOwnsOrder($order, $actor)) {
            abort(403, 'You can only update orders assigned to you.');
        }

        if (! $this->roleAllows($role, $order->status, $newStatus)) {
            abort(403, $this->roleReason($role, $newStatus));
        }
    }

    /**
     * Whether the role's whitelist contains this from -> to pair.
     */
    private function roleAllows(string $role, string $fromStatus, string $toStatus): bool
    {
        return in_array($toStatus, self::ROLE_PERMISSIONS[$role][$fromStatus] ?? [], true);
    }

    /**
     * Explain a role restriction in plain language.
     */
    private function roleReason(string $role, string $newStatus): string
    {
        $label = Order::STATUS_LABELS[$newStatus] ?? ucfirst($newStatus);

        return match ($role) {
            'buyer'            => 'Buyers cannot change an order to ' . $label . '.',
            'seller'           => 'Sellers cannot mark an order as ' . $label
                . ' — that step belongs to the delivery workflow.',
            'delivery_partner' => 'Delivery partners cannot change an order to ' . $label . '.',
            default            => 'You are not allowed to change this order to ' . $label . '.',
        };
    }

    /**
     * The role key used by the permission tables.
     */
    private function roleOf(User $actor): string
    {
        return match (true) {
            $actor->isAdmin()           => 'admin',
            $actor->isSeller()          => 'seller',
            $actor->isDeliveryPartner() => 'delivery_partner',
            default                     => 'buyer',
        };
    }

    /**
     * A seller may only act on orders containing at least one of their lines.
     */
    private function sellerOwnsAnyLine(Order $order, User $seller): bool
    {
        return $order->items()->where('seller_id', $seller->id)->exists();
    }

    /**
     * A delivery partner may only act on their own (assigned) orders.
     */
    private function partnerOwnsOrder(Order $order, User $partner): bool
    {
        return $order->delivery()
            ->where('delivery_partner_id', $partner->id)
            ->exists();
    }

    /**
     * "picked_up" => "picked up" (used inside error messages).
     */
    private function actionPhrase(string $status): string
    {
        return match ($status) {
            'processing'       => 'started processing',
            'ready_for_pickup' => 'marked ready for pickup',
            'assigned'         => 'assigned to a delivery partner',
            'out_for_delivery' => 'out for delivery',
            default            => str_replace('_', ' ', $status),
        };
    }
}
