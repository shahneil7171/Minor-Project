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
        'shipped',
        'delivered',
        'cancelled',
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
     * Position of the current status in the tracking timeline
     * (0 = pending, 1 = processing, 2 = shipped, 3 = delivered).
     */
    public function trackingStep(): int
    {
        $flow = ['pending' => 0, 'processing' => 1, 'shipped' => 2, 'delivered' => 3];

        return $flow[$this->status] ?? 0;
    }
}
