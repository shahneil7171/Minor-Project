<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

class Order extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'user_id',
        'customer_email',
        'order_number',
        'status',
        'approved_at',
        'subtotal',
        'tax',
        'shipping_cost',
        'shipping_method',
        'total',
        'discount_amount',
        'coupon_code',
        'payment_method',
        'shipping_name',
        'shipping_phone',
        'shipping_address',
        'shipping_city',
        'shipping_state',
        'shipping_pincode',
        'shipping_country',
        'notes',
        // Lifecycle timestamps (assigned_at..cancelled_at were added by the
        // order-status-lifecycle migration; approved_at is the confirmation
        // timestamp and is reused — see confirmed_at() below).
        'processing_at',
        'ready_for_pickup_at',
        'assigned_at',
        'picked_up_at',
        'out_for_delivery_at',
        'delivered_at',
        'cancelled_at',
    ];

    /**
     * The attributes that should be cast.
     */
        protected $casts = [
        'subtotal' => 'decimal:2',
        'tax' => 'decimal:2',
        'shipping_cost' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total' => 'decimal:2',
        'approved_at' => 'datetime',
        'processing_at' => 'datetime',
        'ready_for_pickup_at' => 'datetime',
        'assigned_at' => 'datetime',
        'picked_up_at' => 'datetime',
        'out_for_delivery_at' => 'datetime',
        'delivered_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    /**
     * Order lifecycle, in order:
     *
     *   pending -> confirmed -> processing -> ready_for_pickup -> assigned
     *           -> picked_up -> out_for_delivery -> delivered
     *
     * plus the terminal "cancelled" state (only reachable from pending /
     * confirmed). `cancelled` and `delivered` accept no further transition —
     * delivered is the stable anchor the future Return system starts from.
     */
    public const STATUSES = [
        'pending',
        'confirmed',
        'processing',
        'ready_for_pickup',
        'assigned',
        'picked_up',
        'out_for_delivery',
        'delivered',
        'cancelled',
    ];

    /**
     * Human-readable labels for each status (used throughout the UI).
     */
    public const STATUS_LABELS = [
        'pending'          => 'Pending',
        'confirmed'        => 'Confirmed',
        'processing'       => 'Processing',
        'ready_for_pickup' => 'Ready for Pickup',
        'assigned'         => 'Assigned to Delivery Partner',
        'picked_up'        => 'Picked Up',
        'out_for_delivery' => 'Out for Delivery',
        'delivered'        => 'Delivered',
        'cancelled'        => 'Cancelled',
    ];

    /**
     * The forward progress steps (used for tracking timelines).
     * Cancelled is deliberately excluded — it is shown separately.
     */
    public const STATUS_STEPS = [
        'pending',
        'confirmed',
        'processing',
        'ready_for_pickup',
        'assigned',
        'picked_up',
        'out_for_delivery',
        'delivered',
    ];

    /**
     * Timeline wording (the first step reads better as "Order Placed").
     */
    public const STATUS_TIMELINE_LABELS = [
        'pending'          => 'Order Placed',
        'confirmed'        => 'Confirmed',
        'processing'       => 'Processing',
        'ready_for_pickup' => 'Ready for Pickup',
        'assigned'         => 'Delivery Partner Assigned',
        'picked_up'        => 'Picked Up',
        'out_for_delivery' => 'Out for Delivery',
        'delivered'        => 'Delivered',
    ];

    /**
     * Which statuses an order in a given state may transition to.
     *
     * Exactly one forward step is allowed at a time, plus cancellation from
     * pending / confirmed. Nothing may move out of delivered or cancelled.
     * Enforced server-side by App\Services\OrderStatusService.
     */
    public const ALLOWED_TRANSITIONS = [
        'pending'          => ['confirmed', 'cancelled'],
        'confirmed'        => ['processing', 'cancelled'],
        'processing'       => ['ready_for_pickup'],
        'ready_for_pickup' => ['assigned'],
        'assigned'         => ['picked_up'],
        'picked_up'        => ['out_for_delivery'],
        'out_for_delivery' => ['delivered'],
        'delivered'        => [],
        'cancelled'        => [],
    ];

    /**
     * The step an order must already be in for each status to be reachable —
     * used to explain WHY an action is unavailable ("...because it has not
     * been picked up yet.").
     */
    public const STATUS_PRECONDITIONS = [
        'confirmed'        => 'pending',
        'processing'       => 'confirmed',
        'ready_for_pickup' => 'processing',
        'assigned'         => 'ready_for_pickup',
        'picked_up'        => 'assigned',
        'out_for_delivery' => 'picked_up',
        'delivered'        => 'out_for_delivery',
    ];

    /**
     * Statuses the delivery layer owns: they are reached through the
     * assignment + delivery-partner workflow, not through a generic status
     * dropdown.
     */
    public const DELIVERY_OWNED_STATUSES = [
        'assigned',
        'picked_up',
        'out_for_delivery',
        'delivered',
    ];

    /**
     * Which statuses a buyer may cancel their own order from.
     */
    public const CANCELLABLE_STATUSES = [
        'pending',
        'confirmed',
    ];

    /**
     * Status => the order column that records WHEN it happened.
     *
     * "confirmed" maps onto the existing approved_at column (no duplicate
     * confirmed_at column exists — the same moment is reused).
     */
    public const STATUS_TIMESTAMPS = [
        'confirmed'        => 'approved_at',
        'processing'       => 'processing_at',
        'ready_for_pickup' => 'ready_for_pickup_at',
        'assigned'         => 'assigned_at',
        'picked_up'        => 'picked_up_at',
        'out_for_delivery' => 'out_for_delivery_at',
        'delivered'        => 'delivered_at',
        'cancelled'        => 'cancelled_at',
    ];

    /**
     * The user who placed the order.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The items purchased in this order.
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * The delivery assignment for this order (null while unassigned).
     */
    public function delivery(): HasOne
    {
        return $this->hasOne(OrderDelivery::class);
    }

    /**
     * The order's status audit trail (oldest first).
     */
    public function statusHistories(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->orderBy('id');
    }

    /**
     * Every return request raised against this order.
     */
    public function returnRequests(): HasMany
    {
        return $this->hasMany(ReturnRequest::class);
    }

    /**
     * The moment the order was ACTUALLY delivered — the anchor for the
     * return window. Falls back to the delivery row's delivered_at and,
     * for legacy orders without a delivery row, to updated_at so the return
     * window is never silently open forever.
     */
    public function deliveredAt(): ?\Carbon\Carbon
    {
        $this->loadMissing('delivery');

        return $this->delivery?->delivered_at
            ?? ($this->status === 'delivered' ? $this->updated_at : null);
    }

    /**
     * Whether the order has actually been delivered to the buyer.
     */
    public function isDelivered(): bool
    {
        return $this->deliveredAt() !== null;
    }

    /**
     * The last moment a return may be requested for this order.
     */
    public function returnDeadline(): ?\Carbon\Carbon
    {
        return \App\Support\ReturnPolicy::deadline($this->deliveredAt());
    }

    /**
     * Whether the return window for this order has closed.
     */
    public function isReturnWindowExpired(): bool
    {
        return \App\Support\ReturnPolicy::isExpired($this->returnDeadline());
    }

    /**
     * Whether any line of this order can still be returned right now.
     */
    public function hasReturnableItems(): bool
    {
        $this->loadMissing('items');

        return $this->items->contains(
            fn ($item) => \App\Support\ReturnPolicy::check($item)['eligible']
        );
    }

    /**
     * The delivery partner assigned to this order (via the delivery row).
     */
    public function deliveryPartner(): HasOneThrough
    {
        return $this->hasOneThrough(
            User::class,
            OrderDelivery::class,
            'order_id',
            'id',
            'id',
            'delivery_partner_id',
        );
    }

    /**
     * Whether the order has been confirmed by an admin (or is already past
     * the confirmation step).
     *
     * "Approved" is the legacy name for the confirmation step: approved_at /
     * isApproved() are kept working so existing mails, views and reporting
     * keep functioning unchanged.
     */
    public function isApproved(): bool
    {
        return self::stepIndex($this->status) >= self::stepIndex('confirmed');
    }

    /**
     * Alias of isApproved() under the canonical lifecycle name.
     */
    public function isConfirmed(): bool
    {
        return $this->isApproved();
    }

    /**
     * Whether the order was cancelled.
     */
    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    /**
     * Whether the order reached its terminal delivered state.
     */
    public function isDeliveredStatus(): bool
    {
        return $this->status === 'delivered';
    }

    /**
     * Human-readable label for the current status.
     */
    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? ucfirst((string) $this->status);
    }

    /**
     * Whether the lifecycle itself allows this order to move to $status
     * (role permissions are enforced separately by OrderStatusService).
     */
    public function canTransitionTo(?string $status): bool
    {
        if ($status === null) {
            return false;
        }

        return in_array($status, self::ALLOWED_TRANSITIONS[$this->status] ?? [], true);
    }

    /**
     * Position of a status on the forward timeline
     * (-1 for cancelled/failed/unknown values).
     */
    public static function stepIndex(?string $status): int
    {
        $flow = array_flip(self::STATUS_STEPS);

        return $flow[$status] ?? -1;
    }

    /**
     * The statuses this order may legitimately move to next (lifecycle only).
     *
     * @return array<int, string>
     */
    public function allowedNextStatuses(): array
    {
        return self::ALLOWED_TRANSITIONS[$this->status] ?? [];
    }

    /**
     * The timestamp column holding the moment the given status was reached.
     */
    public static function timestampColumnFor(string $status): ?string
    {
        return self::STATUS_TIMESTAMPS[$status] ?? null;
    }

    /**
     * When the given lifecycle step happened (null while it is still ahead).
     */
    public function statusTimestamp(string $status): ?\Carbon\Carbon
    {
        $column = self::timestampColumnFor($status);

        if ($column === null) {
            return null;
        }

        $value = $this->{$column};

        return $value instanceof \Carbon\Carbon ? $value : null;
    }

    /**
     * The moment the order was confirmed. Reuses the existing approved_at
     * column (the same moment) so no duplicate column is needed.
     */
    public function confirmedAt(): ?\Carbon\Carbon
    {
        return $this->statusTimestamp('confirmed');
    }

    /**
     * Read-only `confirmed_at` attribute so views/tests can use the canonical
     * lifecycle name while the database keeps the original column name.
     */
    public function getConfirmedAtAttribute(): ?\Carbon\Carbon
    {
        return $this->confirmedAt();
    }

    /**
     * Whether the buyer (or an admin) may still cancel this order.
     */
    public function isCancellable(): bool
    {
        return in_array($this->status, self::CANCELLABLE_STATUSES, true);
    }

    /**
     * Position of the current status in the tracking timeline.
     */
    public function trackingStep(): int
    {
        return self::stepIndex($this->status);
    }
}
