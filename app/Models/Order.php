<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
    ];

    /**
     * Order lifecycle steps in tracking order.
     */
    public const STATUSES = [
        'pending',
        'processing',
        'packed',
        'shipped',
        'delivered',
        'cancelled',
    ];

    /**
     * Human-readable labels for each status (used throughout the UI).
     */
    public const STATUS_LABELS = [
        'pending'    => 'Pending',
        'processing' => 'Processing',
        'packed'     => 'Packed',
        'shipped'    => 'Shipped',
        'delivered'  => 'Delivered',
        'cancelled'  => 'Cancelled',
    ];

    /**
     * The forward progress steps (used for the tracking timeline).
     * Cancelled is deliberately excluded — it is shown separately.
     */
    public const STATUS_STEPS = [
        'pending',
        'processing',
        'packed',
        'shipped',
        'delivered',
    ];

    /**
     * Which statuses an order in a given state may transition to.
     *
     * The normal forward flow is pending -> processing -> packed -> shipped ->
     * delivered. Cancellation is allowed from any pre-delivery state. We also
     * permit skipping forward steps and staying on the same status, but we do
     * not allow backwards moves or any move out of delivered/cancelled.
     */
    public const ALLOWED_TRANSITIONS = [
        'pending'    => ['processing', 'packed', 'shipped', 'delivered', 'cancelled'],
        'processing' => ['packed', 'shipped', 'delivered', 'cancelled'],
        'packed'     => ['shipped', 'delivered', 'cancelled'],
        'shipped'    => ['delivered'],
        'delivered'  => [],
        'cancelled'  => [],
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
     * Whether the order was cancelled.
     */
    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    /**
     * Human-readable label for the current status.
     */
    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? ucfirst((string) $this->status);
    }

    /**
     * Whether this order may be moved to the given status.
     */
    public function canTransitionTo(?string $status): bool
    {
        if ($status === null) {
            return false;
        }

        // Staying on the same status is always allowed (a no-op submit).
        if ($status === $this->status) {
            return true;
        }

        return in_array($status, self::ALLOWED_TRANSITIONS[$this->status] ?? [], true);
    }

    /**
     * Position of the current status in the tracking timeline
     * (0 = pending, 1 = processing, 2 = packed, 3 = shipped, 4 = delivered).
     */
    public function trackingStep(): int
    {
        $flow = array_flip(self::STATUS_STEPS);

        return $flow[$this->status] ?? 0;
    }
}
