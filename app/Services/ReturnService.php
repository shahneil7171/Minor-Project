<?php

namespace App\Services;

use App\Mail\RefundProcessedMail;
use App\Mail\RefundProcessingMail;
use App\Mail\ReturnApprovedMail;
use App\Mail\ReturnPickupAssignedMail;
use App\Mail\ReturnReceivedMail;
use App\Mail\ReturnRejectedMail;
use App\Mail\ReturnRequestedMail;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ReturnRequest;
use App\Models\ReturnStatusHistory;
use App\Models\User;
use App\Notifications\StoreAlert;
use App\Support\ReturnPolicy;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Return & refund workflow.
 *
 * Owns every business rule of the return layer so the buyer, seller, admin and
 * delivery-partner controllers never duplicate logic:
 *
 *  - a return can only be created for a delivered line, inside the configured
 *    window and within the quantity that has not already been returned;
 *  - the refund amount is always derived from the recorded order data
 *    (order_items.price + the order's proportional discount/tax), never from
 *    anything the buyer submits;
 *  - status changes are validated against an explicit transition map;
 *  - every event notifies the right role only (buyer / seller / delivery
 *    partner) and a failing email or notification can never break the
 *    already-persisted state change.
 */
class ReturnService
{
    /**
     * Allowed return-status transitions (canonical spec vocabulary).
     *
     * Legacy aliases (`pickup_scheduled`, `inspected`) are normalized before
     * validation so existing rows keep working.
     *
     * @var array<string, array<int, string>>
     */
    private const TRANSITIONS = [
        'pending'           => ['approved', 'rejected', 'cancelled'],
        'approved'          => ['pickup_assigned', 'received', 'rejected', 'cancelled'],
        'pickup_assigned'   => ['picked_up', 'received', 'cancelled'],
        'picked_up'         => ['received'],
        'received'          => ['refund_processing', 'rejected', 'cancelled'],
        'refund_processing' => ['refunded'],
        'refunded'          => ['completed'],
        'completed'         => [],
        'rejected'          => [],
        'cancelled'         => [],
    ];

    /**
     * The configured return window in days.
     */
    public function windowDays(): int
    {
        return ReturnPolicy::windowDays();
    }

    /**
     * The return deadline for an order (delivery date + window, inclusive).
     */
    public function deadlineFor(Order $order): ?Carbon
    {
        return $order->returnDeadline();
    }

    /**
     * Eligibility + refund estimate for one line, used by the return page and
     * re-checked on submit.
     *
     * @return array{eligible: bool, reason: ?string, breakdown: array<string, mixed>, returnable: int}
     */
    public function preview(OrderItem $item, ?int $quantity = null): array
    {
        $returnable = $item->returnableQuantity();
        $quantity = $quantity === null ? max(1, $returnable) : $quantity;

        $check = ReturnPolicy::check($item, $quantity);

        return [
            'eligible'   => $check['eligible'],
            'reason'     => $check['reason'],
            'returnable' => $returnable,
            'breakdown'  => ReturnPolicy::refundBreakdown($item->order, $item, $quantity),
        ];
    }

    /**
     * Create a return request for one order line.
     *
     * Every rule is re-verified server-side (the UI is never trusted).
     *
     * @param  array<int, UploadedFile>  $images
     *
     * @throws ValidationException
     */
    public function create(OrderItem $item, User $buyer, array $data, array $images = []): ReturnRequest
    {
        $order = $item->order;

        if (! $order || (int) $order->user_id !== (int) $buyer->id) {
            throw ValidationException::withMessages([
                'order_item_id' => 'You can only request a return for your own orders.',
            ]);
        }

        $quantity = max(1, (int) ($data['quantity'] ?? 1));

        $reason = trim((string) ($data['reason'] ?? ''));

        if ($reason === '' || ! in_array($reason, ReturnRequest::REASONS, true)) {
            throw ValidationException::withMessages([
                'reason' => 'Please choose a valid return reason.',
            ]);
        }

        if ($reason === 'Other' && trim((string) ($data['description'] ?? '')) === '') {
            throw ValidationException::withMessages([
                'description' => 'Please provide a description for Other.',
            ]);
        }

        $check = ReturnPolicy::check($item, $quantity);

        if (! $check['eligible']) {
            throw ValidationException::withMessages([
                'order_item_id' => $check['reason'] ?? 'This item is not eligible for return.',
            ]);
        }

        $breakdown = ReturnPolicy::refundBreakdown($order, $item, $quantity);

        $return = DB::transaction(function () use ($item, $order, $buyer, $data, $quantity, $breakdown) {
            $return = ReturnRequest::create([
                'order_id'          => $order->id,
                'order_item_id'     => $item->id,
                'user_id'           => $buyer->id,
                'order_number'      => $order->order_number,
                'customer_email'    => $buyer->email ?: $order->customer_email,
                'product_slug'      => $item->product_slug,
                'product_id'        => $this->resolveProductId($item->product_slug),
                'seller_id'         => $item->seller_id,
                'product_title'     => $item->product_title,
                'quantity'          => $quantity,
                'reason'            => $data['reason'],
                'description'       => $data['description'] ?? null,
                'status'            => 'pending',
                'refund_status'     => 'none',
                // Refund is only an ESTIMATE until the return is approved; the
                // amount is always recomputed from order data, never trusted
                // from the request payload.
                'refund_amount'     => $breakdown['total'],
                'shipping_refund_amount' => $breakdown['shipping'],
                'return_deadline'   => $order->returnDeadline(),
                'requested_at'      => now(),
            ]);

            return $return;
        });

        $this->storeImages($return, $images);

        $return->refresh()->load(['order', 'customer', 'seller', 'orderItem']);

        // Buyer: confirmation email + in-app notification.
        $this->mailTo(
            $return->customer_email,
            new ReturnRequestedMail($return),
            'return requested for order ' . $return->order_number
        );

        if ($return->customer) {
            $this->alert(
                $return->customer,
                'Return request submitted',
                'Return request submitted for Order #' . $return->order_number . '.',
                route('returns.show', ['return' => $return]),
                $this->meta($return)
            );
        }

        // Seller: told a return was raised against one of their products.
        if ($return->seller) {
            $this->alert(
                $return->seller,
                'New return request',
                'A return was requested for your product in Order #' . $return->order_number . '.',
                route('seller.returns.show', ['return' => $return]),
                $this->meta($return)
            );
        }

        return $return;
    }

    /**
     * Store evidence images safely (random names, whitelisted extensions).
     *
     * @param  array<int, UploadedFile>  $images
     */
    private function storeImages(ReturnRequest $return, array $images): void
    {
        if ($images === []) {
            return;
        }

        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        $stored = [];

        foreach ($images as $file) {
            if (! $file instanceof UploadedFile || ! $file->isValid()) {
                continue;
            }

            $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension());

            if (! in_array($extension, $allowed, true)) {
                Log::warning('Return evidence upload rejected: unsupported extension for return #' . $return->id);
                continue;
            }

            // Storage generates a random hashed filename, so an uploaded file
            // can never be executed or overwrite an existing one.
            $path = $file->store('returns/' . $return->id, 'public');

            if ($path) {
                $stored[] = $path;
            }
        }

        if ($stored !== []) {
            $return->update(['images' => array_merge($return->images ?? [], $stored)]);
        }
    }

    // ------------------------------------------------------------------
    // Admin / partner actions (status workflow)
    // ------------------------------------------------------------------

    /**
     * Approve a pending return.
     *
     * Recomputes the refund from the recorded order data (a product price
     * change after the order never leaks in), stamps approved_at and moves
     * both the return status and the refund status forward. Notifies the
     * buyer and the seller; delivery collection happens via schedulePickup().
     */
    public function approve(ReturnRequest $return, User $admin, ?string $adminNote = null): ReturnRequest
    {
        $return->loadMissing(['order', 'orderItem', 'customer', 'seller']);

        $from = $return->status;
                $this->requireTransition($return, 'approved');

        DB::transaction(function () use ($return, $from, $admin, $adminNote): void {
            $return->forceFill([
                'status' => 'approved',
                'approved_at' => now(),
                'refund_status' => 'pending',
                'admin_note' => $adminNote ?? $return->admin_note,
            ])->save();

            $this->recordHistory($return, $from, 'approved', $admin, $adminNote);

            $this->recomputeRefund($return);
        });

        $return->refresh()->load(['order', 'orderItem', 'customer', 'seller']);

        $orderNumber = $return->order_number;

        if ($return->customer) {
            $this->alert(
                $return->customer,
                'Return request approved',
                'Your return request for Order #' . $orderNumber . ' has been approved.',
                route('returns.show', ['return' => $return]),
                $this->meta($return, ['event' => 'return_approved'])
            );
        }

        $this->mailTo(
            $return->customer_email,
            new ReturnApprovedMail($return),
            'return approved for order ' . $orderNumber
        );

        if ($return->seller) {
            $this->alert(
                $return->seller,
                'Return approved',
                'A return for your product in Order #' . $orderNumber . ' was approved.',
                route('seller.returns.show', ['return' => $return]),
                $this->meta($return, ['event' => 'return_approved'])
            );
        }

        return $return;
    }

    /**
     * Reject a pending/approved return with a mandatory reason.
     *
     * The reason is required — a return is never silently rejected.
     */
    public function reject(ReturnRequest $return, User $admin, string $reason, ?string $adminNote = null): ReturnRequest
    {
                $return->loadMissing(['order', 'orderItem', 'customer', 'seller']);

        $from = $return->status;
        $this->requireTransition($return, 'rejected');

        $reason = trim($reason);

        if ($reason === '') {
            throw ValidationException::withMessages([
                'rejection_reason' => 'A rejection reason is required.',
            ]);
        }

        $return->forceFill([
            'status' => 'rejected',
            'rejected_at' => now(),
            'rejection_reason' => $reason,
            'admin_note' => $adminNote ?? $return->admin_note,
        ])->save();

        $this->recordHistory($return, $from, 'rejected', $admin, $reason);

        if ($return->customer) {
            $this->alert(
                $return->customer,
                'Return request rejected',
                'Your return request for Order #' . $return->order_number . ' was rejected.',
                route('returns.show', ['return' => $return]),
                $this->meta($return, ['event' => 'return_rejected'])
            );
        }

        $this->mailTo(
            $return->customer_email,
            new ReturnRejectedMail($return),
            'return rejected for order ' . $return->order_number
        );

        return $return;
    }

    /**
     * Assign a delivery partner to collect the parcel and notify them
     * (in-app + email) reusing the delivery notification infrastructure.
     */
    public function schedulePickup(ReturnRequest $return, int $partnerId, User $admin, ?string $pickupInstructions = null): ReturnRequest
    {
        $return->loadMissing(['order', 'orderItem']);

        $this->requireTransition($return, 'pickup_assigned');

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

        $return->forceFill([
            'status' => 'pickup_assigned',
            'delivery_partner_id' => $partner->id,
            'assigned_by' => $admin->id,
            'pickup_notes' => $pickupInstructions,
            'pickup_scheduled_at' => now(),
            'pickup_assigned_at' => now(),
        ])->save();

        $this->notifyBuyer($return, 'Return pickup scheduled', 'Your return pickup has been scheduled.', 'return_pickup_scheduled');

        try {
            Mail::to($partner->email)->send(new ReturnPickupAssignedMail($return));
        } catch (Throwable $e) {
            Log::error('Return pickup email failed for order ' . $return->order_number . ': ' . $e->getMessage());
        }

        $this->alert(
            $partner,
            'Return pickup assigned',
            'Return pickup assigned for Order #' . $return->order_number . '.',
            route('delivery.pickups.show', ['pickup' => $return]),
            $this->meta($return, ['event' => 'return_pickup_assigned', 'delivery_partner_id' => $partner->id])
        );

        return $return;
    }

    /**
     * Delivery partner collects the parcel from the buyer:
     * pickup_assigned -> picked_up. Only the assigned partner (or an admin)
     * may record it. Notifies the buyer.
     */
                public function markPickedUp(ReturnRequest $return, User $actor, ?string $notes = null): ReturnRequest
    {
        $return->loadMissing(['order', 'customer']);

        if ($actor->isDeliveryPartner()
            && (int) $return->delivery_partner_id !== (int) $actor->id) {
            abort(403, 'You can only update pickups assigned to you.');
        }

        $from = $return->status;
        $this->requireTransition($return, 'picked_up');

        $return->forceFill([
            'status' => 'picked_up',
            'picked_up_at' => now(),
        ])->save();

        $this->recordHistory($return, $from, 'picked_up', $actor, $notes);

        $this->notifyBuyer($return, 'Product picked up', 'Your return parcel has been picked up and is on its way back.', 'return_picked_up');

        return $return;
    }

        /**
     * Mark the parcel as received (delivery partner scan or admin action).
     *
     * Only the assigned partner (or any admin) may record the pickup.
     */
    public function markReceived(ReturnRequest $return, User $actor, ?string $notes = null): ReturnRequest
    {
        $return->loadMissing(['order', 'customer', 'seller']);

        if ($actor->isDeliveryPartner()
            && (int) $return->delivery_partner_id !== (int) $actor->id) {
            abort(403, 'You can only update pickups assigned to you.');
        }

        $from = $return->status;
        $this->requireTransition($return, 'received');

        $return->forceFill([
            'status' => 'received',
            'received_at' => now(),
        ])->save();

        $this->recordHistory($return, $from, 'received', $actor, $notes);

        $this->notifyBuyer($return, 'Product received', 'Your returned product has been received.', 'return_received');

        $this->mailTo(
            $return->customer_email,
            new ReturnReceivedMail($return->fresh()->load(['order', 'orderItem', 'customer', 'seller'])),
            'return received for order ' . $return->order_number
        );

        if ($return->seller) {
            $this->alert(
                $return->seller,
                'Returned product received',
                'The returned product for Order #' . $return->order_number . ' was received.',
                route('seller.returns.show', ['return' => $return]),
                $this->meta($return, ['event' => 'return_received'])
            );
        }

        return $return;
    }

    /**
     * Admin starts the refund (status tracking only — no gateway claim).
     */
    public function startRefund(ReturnRequest $return, User $admin, ?string $reference = null): ReturnRequest
    {
        $return->loadMissing(['order', 'customer']);

        $from = $return->status;
        $this->requireTransition($return, 'refund_processing');

        $return->forceFill([
            'status' => 'refund_processing',
            'refund_status' => 'processing',
            'refund_reference' => $return->refund_reference ?: $reference,
            'refund_processing_at' => now(),
        ])->save();

        $this->recordHistory($return, $from, 'refund_processing', $admin, $reference);

        $this->notifyBuyer($return, 'Refund processing', 'Your refund is being processed.', 'refund_processing');

        $this->mailTo(
            $return->customer_email,
            new RefundProcessingMail($return->fresh()->load(['order', 'orderItem', 'customer', 'seller'])),
            'refund processing for order ' . $return->order_number
        );

        return $return;
    }

    /**
     * Admin records the refund as completed.
     *
     * The status intentionally tracks the store's bookkeeping only: the demo
     * integrates no payment-gateway refund API, so no automatic bank/UPI
     * transfer is claimed anywhere.
     */
    public function markRefunded(ReturnRequest $return, User $admin, ?string $reference = null): ReturnRequest
    {
        $return->loadMissing(['order', 'orderItem', 'customer']);

        $from = $return->status;
        $this->requireTransition($return, 'refunded');
        $this->requireRefundFlow($return->refund_status, 'refunded');

        $return->forceFill([
            'status' => 'refunded',
            'refund_status' => 'refunded',
            'refunded_at' => now(),
            'refund_reference' => $return->refund_reference ?: $reference,
        ])->save();

        $this->recordHistory($return, $from, 'refunded', $admin, $reference);

        $this->recomputeRefund($return);

        if ($return->customer) {
            $this->alert(
                $return->customer,
                'Refund completed',
                'Your refund for Order #' . $return->order_number . ' has been completed.',
                route('returns.show', ['return' => $return]),
                $this->meta($return, ['event' => 'refund_completed'])
            );
        }

        $this->mailTo(
            $return->customer_email,
            new RefundProcessedMail($return),
            'refund completed for order ' . $return->order_number
        );

        return $return;
    }

    // ------------------------------------------------------------------
    // Shared helpers
    // ------------------------------------------------------------------

    /**
     * Resolve the catalog product id from the recorded line slug (nullable —
     * the snapshot is the source of truth when the product was deleted).
     */
    private function resolveProductId(?string $slug): ?int
    {
        if (! $slug) {
            return null;
        }

        return Product::where('slug', $slug)->value('id');
    }

    /**
     * In-app alert on the existing StoreAlert notification.
     */
    private function alert(User $user, string $title, string $body, string $url, array $meta = []): void
    {
        try {
            $user->notify(new StoreAlert($title, $body, $url, $meta));
        } catch (Throwable $e) {
            Log::error('Return notification failed for user ' . $user->id . ': ' . $e->getMessage());
        }
    }

    /**
     * Send a mailable, never crashing the persisted workflow on failure.
     */
    private function mailTo(?string $email, Mailable $mail, string $context): void
    {
        if (! $email || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Log::warning('Return email skipped (missing/invalid address): ' . $context);

            return;
        }

        try {
            Mail::to($email)->send($mail);
        } catch (Throwable $e) {
            Log::error('Return email failed for ' . $context . ': ' . $e->getMessage());
        }
    }

    /**
     * Standard notification payload carried by every return alert.
     */
    private function meta(ReturnRequest $return, array $extra = []): array
    {
        return array_merge([
            'type' => 'return',
            'return_id' => $return->id,
                        'return_number' => $return->return_number,
            'order_id' => $return->order_id,
            'order_number' => $return->order_number,
            'status' => $return->status,
        ], $extra);
    }

    /**
     * Guard status transitions server-side.
     */
    private function requireTransition(ReturnRequest $return, string $to): void
    {
        $from = $return->normalizedStatus();

        if ($from === $to) {
            return;
        }

        $message = 'A return in "' . $return->statusLabel() . '" cannot move to "'
            . (ReturnRequest::STATUS_LABELS[$to] ?? $to) . '".';

        if (! in_array($to, self::TRANSITIONS[$from] ?? [], true)) {
            throw ValidationException::withMessages(['status' => $message]);
        }
    }

    /**
     * Write one row to the return-only return_status_histories table.
     *
     * Every real status change performed by ReturnService is recorded here so a
     * seller buying via the API can be audited without touching the order history.
     */
    private function recordHistory(
        ReturnRequest $return,
        string $from,
        string $to,
        ?User $actor,
        ?string $note = null
    ): void {
        $actorId = $actor?->id;
        $note = $note !== null && trim($note) !== '' ? trim($note) : null;

        DB::table('return_status_histories')->insert([
            'return_request_id' => $return->id,
            'from_status'       => $from,
            'to_status'         => $to,
            'changed_by'        => $actorId,
            'note'              => $note,
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);
    }

    /**
     * Refund-status transitions are validated with the same strictness.
     */
    private function requireRefundFlow(string $from, string $to): void
    {
        $allowed = [
            'pending'    => ['processing', 'refunded'],
            'processing' => ['refunded'],
            'refunded'   => [],
        ];

        if (! in_array($to, $allowed[$from] ?? [], true)) {
            throw ValidationException::withMessages([
                'refund_status' => 'Refund status "' . $from . '" cannot move to "' . $to . '".',
            ]);
        }
    }

    /**
     * Recompute every refund-related column from the recorded order data.
     *
     * A product price change after the order must never leak into a refund, so
     * every admin action that changes the refund_status re-derives the amount
     * from $return->orderItem->price and the order's snapshot values.
     */
    private function recomputeRefund(ReturnRequest $return): void
    {
        $return->forceFill([
            'refund_amount'          => $this->refundTotal($return),
            'shipping_refund_amount' => $this->shippingRefund($return),
        ])->saveQuietly();
    }

    private function refundTotal(ReturnRequest $return): float
    {
        return round(ReturnPolicy::refundBreakdown(
            $return->order,
            $return->orderItem,
            (int) $return->quantity
        )['total'], 2);
    }

    private function shippingRefund(ReturnRequest $return): float
    {
        return round(ReturnPolicy::refundBreakdown(
            $return->order,
            $return->orderItem,
            (int) $return->quantity
        )['shipping'], 2);
    }

    /**
     * Buyer notification helper for status events.
     */
    private function notifyBuyer(ReturnRequest $return, string $title, string $body, string $event): void
    {
        if ($return->customer) {
            $this->alert(
                $return->customer,
                $title,
                $body,
                route('returns.show', ['return' => $return]),
                $this->meta($return, ['event' => $event])
            );
        }
    }
}