<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A delivery assignment for an order.
 *
 * One normalized row per order (unique order_id) — reassignment updates the
 * row instead of creating duplicates. The delivery status lifecycle is
 * deliberately separate from the order status lifecycle:
 *
 *   assigned -> ready_for_pickup -> picked_up -> out_for_delivery -> delivered
 *                                              \-> failed (from any active state)
 */
class OrderDelivery extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'order_id',
        'delivery_partner_id',
        'assigned_by',
        'status',
        'assigned_at',
        'picked_up_at',
        'out_for_delivery_at',
        'delivered_at',
        'failed_at',
        'delivery_notes',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'assigned_at' => 'datetime',
        'picked_up_at' => 'datetime',
        'out_for_delivery_at' => 'datetime',
        'delivered_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    /**
     * Delivery lifecycle statuses.
     */
    public const STATUSES = [
        'assigned',
        'ready_for_pickup',
        'picked_up',
        'out_for_delivery',
        'delivered',
        'failed',
    ];

    /**
     * Human-readable labels for each delivery status.
     */
    public const STATUS_LABELS = [
        'assigned'         => 'Assigned',
        'ready_for_pickup' => 'Ready for Pickup',
        'picked_up'        => 'Picked Up',
        'out_for_delivery' => 'Out for Delivery',
        'delivered'        => 'Delivered',
        'failed'           => 'Failed',
    ];

    /**
     * Which statuses a delivery in a given state may transition to.
     *
     * "ready_for_pickup" is normally set by the seller-side workflow (order
     * packed) but partners may also pick up directly from "assigned".
     * "failed" is reachable from any active state; terminal states accept
     * nothing.
     */
    public const ALLOWED_TRANSITIONS = [
        'assigned'         => ['ready_for_pickup', 'picked_up', 'failed'],
        'ready_for_pickup' => ['picked_up', 'failed'],
        'picked_up'        => ['out_for_delivery', 'failed'],
        'out_for_delivery' => ['delivered', 'failed'],
        'delivered'        => [],
        'failed'           => [],
    ];

    /**
     * The order being delivered.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * The delivery partner responsible for this delivery.
     */
    public function deliveryPartner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delivery_partner_id');
    }

    /**
     * The admin who assigned this delivery.
     */
    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    /**
     * Human-readable label for the current delivery status.
     */
    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? ucfirst((string) $this->status);
    }

    /**
     * Whether this delivery may be moved to the given status.
     */
    public function canTransitionTo(string $status): bool
    {
        if (! in_array($status, self::STATUSES, true)) {
            return false;
        }

        // Staying on the same status is a no-op that never re-notifies.
        if ($status === $this->status) {
            return true;
        }

        return in_array($status, self::ALLOWED_TRANSITIONS[$this->status] ?? [], true);
    }

    /**
     * Whether the delivery reached its final "delivered" state.
     */
    public function isDelivered(): bool
    {
        return $this->status === 'delivered';
    }

    /**
     * Whether the delivery was marked as failed.
     */
    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Whether the delivery is still being processed (not delivered/failed).
     */
    public function isActive(): bool
    {
        return ! in_array($this->status, ['delivered', 'failed'], true);
    }
}
