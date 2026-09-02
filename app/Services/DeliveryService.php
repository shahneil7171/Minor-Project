<?php

namespace App\Services;

use App\Mail\DeliveryAssignedMail;
use App\Mail\DeliveryReadyForPickupMail;
use App\Mail\OrderDeliveredMail;
use App\Mail\OrderShippedMail;
use App\Mail\SellerOrderPickedUpMail;
use App\Models\Order;
use App\Models\OrderDelivery;
use App\Models\User;
use App\Notifications\StoreAlert;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;
use Illuminate\Validation\ValidationException;

/**
 * Delivery assignment & lifecycle service.
 *
 * Owns every rule of the delivery layer so the admin, seller and delivery
 * partner controllers never duplicate business logic:
 *
 *  - Assignment creates exactly one order_deliveries row per order (unique
 *    order_id); reassignment UPDATES that row and notifies both partners.
 *  - Only ACTIVE delivery partners can be assigned.
 *  - Transitions are validated server-side against OrderDelivery's allowed
 *    transitions, and a delivery partner may only ever update their own
 *    assignment (403 otherwise — never only hidden buttons).
 *  - Status side effects: out_for_delivery moves the order to "shipped",
 *    delivered moves it to "delivered" (reusing the existing buyer mails),
 *    picked_up notifies the sellers.
 */
class DeliveryService
{
    /**
     * Assign (or reassign) an active delivery partner to an order.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function assign(Order $order, int $partnerId, User $admin): OrderDelivery
    {
        $partner = User::where('account_type', 'delivery_partner')->find($partnerId);

        if (! $partner) {
            throw ValidationException::withMessages([
                'delivery_partner_id' => 'The selected user is not a delivery partner.',
            ]);
        }

        if (! $partner->isActive()) {
            throw ValidationException::withMessages([
                'delivery_partner_id' => 'Only active delivery partners can be assigned.',
            ]);
        }

        $delivery = OrderDelivery::firstOrNew(['order_id' => $order->id]);
        $previousPartnerId = $delivery->exists ? $delivery->delivery_partner_id : null;
        $isReassignment = $previousPartnerId !== null && (int) $previousPartnerId !== $partner->id;

        // A fresh assignment (or reassignment) always restarts the lifecycle.
        $delivery->fill([
            'delivery_partner_id' => $partner->id,
            'assigned_by'         => $admin->id,
            'status'              => 'assigned',
            'assigned_at'         => now(),
            'picked_up_at'        => null,
            'out_for_delivery_at' => null,
            'delivered_at'        => null,
            'failed_at'           => null,
        ])->save();

        $order->refresh()->load('items');

        try {
            Mail::to($partner->email)->send(new DeliveryAssignedMail($delivery, $order, $isReassignment));
        } catch (Throwable $e) {
            Log::error('Delivery assignment email failed for order ' . $order->order_number . ': ' . $e->getMessage());
        }

        $partner->notify(new StoreAlert(
            $isReassignment ? 'Delivery reassigned to you' : 'New delivery assigned',
            'Order #' . $order->order_number . ' has been assigned to you.',
            route('delivery.deliveries.show', ['delivery' => $delivery->id]),
        ));

        if ($isReassignment) {
            User::find($previousPartnerId)?->notify(new StoreAlert(
                'Delivery reassigned',
                'Order #' . $order->order_number . ' has been reassigned to another delivery partner.',
                route('delivery.dashboard'),
            ));
        }

        return $delivery;
    }

    /**
     * Move a delivery to the next lifecycle status.
     *
     * Authorization is enforced here (server-side): delivery partners can
     * only update their own assignment; admins may additionally mark a
     * delivery as failed.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function transition(OrderDelivery $delivery, string $to, User $actor, ?string $notes = null): void
    {
        if ($actor->isDeliveryPartner()) {
            if ((int) $delivery->delivery_partner_id !== $actor->id) {
                abort(403, 'You can only update deliveries assigned to you.');
            }
        } elseif (! $actor->isAdmin()) {
            abort(403, 'You are not allowed to update deliveries.');
        }

        if (! $delivery->canTransitionTo($to)) {
            throw ValidationException::withMessages([
                'status' => 'Cannot change delivery status from ' . $delivery->statusLabel()
                    . ' to ' . (OrderDelivery::STATUS_LABELS[$to] ?? $to) . '.',
            ]);
        }

        // Same-status submit (double click / refresh): no change, no emails.
        if ($to === $delivery->status) {
            return;
        }

        $attributes = ['status' => $to];

        match ($to) {
            'picked_up'        => $attributes['picked_up_at'] = now(),
            'out_for_delivery' => $attributes['out_for_delivery_at'] = now(),
            'delivered'        => $attributes['delivered_at'] = now(),
            'failed'           => $attributes['failed_at'] = now(),
            default            => null,
        };

        if ($notes !== null && trim($notes) !== '') {
            $attributes['delivery_notes'] = trim(($delivery->delivery_notes ? $delivery->delivery_notes . "\n" : '') . trim($notes));
        }

        DB::transaction(function () use ($delivery, $attributes, $to): void {
            $delivery->update($attributes);

            $order = $delivery->order()->with(['items', 'user'])->first();

            if ($to === 'out_for_delivery' && $order->canTransitionTo('shipped')) {
                $order->update(['status' => 'shipped']);
                $this->emailBuyerStatus($order, 'shipped');
            }

            if ($to === 'delivered' && $order->canTransitionTo('delivered')) {
                $order->update(['status' => 'delivered']);
                $this->emailBuyerStatus($order, 'delivered');
            }

            if ($to === 'picked_up') {
                $this->notifySellersOfPickup($order);
            }
        });
    }

    /**
     * Move an assigned delivery to "ready_for_pickup" and alert the partner.
     *
     * Called by the seller workflow when the order is packed. Only fires from
     * "assigned" so repeated seller updates never spam the partner.
     */
    public function markReadyForPickup(OrderDelivery $delivery): void
    {
        if ($delivery->status !== 'assigned') {
            return;
        }

        $delivery->update(['status' => 'ready_for_pickup']);

        $delivery->load(['order', 'deliveryPartner']);
        $partner = $delivery->deliveryPartner;

        if (! $partner) {
            return;
        }

        try {
            Mail::to($partner->email)->send(new DeliveryReadyForPickupMail($delivery));
        } catch (Throwable $e) {
            Log::error('Ready-for-pickup email failed for order ' . $delivery->order->order_number . ': ' . $e->getMessage());
        }

        $partner->notify(new StoreAlert(
            'Order ready for pickup',
            'Order #' . $delivery->order->order_number . ' is packed and ready for pickup.',
            route('delivery.deliveries.show', ['delivery' => $delivery->id]),
        ));
    }

    /**
     * Reuse the existing buyer status emails for delivery-driven changes.
     */
    private function emailBuyerStatus(Order $order, string $status): void
    {
        $recipient = $order->user?->email ?? $order->customer_email;

        if (! $recipient) {
            return;
        }

        try {
            if ($status === 'shipped') {
                Mail::to($recipient)->send(new OrderShippedMail($order, now()));
            } else {
                Mail::to($recipient)->send(new OrderDeliveredMail($order, now()));
            }
        } catch (Throwable $e) {
            Log::error('Delivery status email failed for order ' . $order->order_number . ': ' . $e->getMessage());
        }
    }

    /**
     * Notify every seller with lines in the order that it was picked up.
     */
    private function notifySellersOfPickup(Order $order): void
    {
        $order->items
            ->filter(fn ($item) => $item->seller_id !== null)
            ->groupBy('seller_id')
            ->each(function ($items, int $sellerId) use ($order): void {
                $seller = User::find($sellerId);

                if (! $seller) {
                    return;
                }

                try {
                    Mail::to($seller->email)->send(new SellerOrderPickedUpMail($order, $items->all()));
                } catch (Throwable $e) {
                    Log::error('Seller pickup email failed for order ' . $order->order_number . ': ' . $e->getMessage());
                }

                $seller->notify(new StoreAlert(
                    'Order picked up',
                    'Order #' . $order->order_number . ' has been picked up by the delivery partner.',
                    route('seller.orders.show', ['order' => $order->id]),
                ));
            });
    }
}
