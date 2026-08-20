<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

/**
 * Coupon / Promo-code model.
 *
 * Supports fixed-amount and percentage discounts that can be applied by a
 * shopper on the checkout review step. Validity (active, expiry, usage limit,
 * minimum order) is evaluated against the order subtotal at the moment the order
 * is persisted.
 */
class Coupon extends Model
{
    /** Discount strategies. */
    public const FIXED = 'fixed';
    public const PERCENT = 'percent';

    public const TYPES = [self::FIXED, self::PERCENT];

    protected $fillable = [
        'code',
        'type',
        'value',
        'usage_limit',
        'used_count',
        'min_order_amount',
        'expires_at',
        'active',
    ];

    protected $casts = [
        'type'             => 'string',
        'value'            => 'integer',
        'usage_limit'      => 'integer',
        'used_count'       => 'integer',
        'min_order_amount' => 'decimal:2',
        'expires_at'       => 'datetime',
        'active'           => 'boolean',
    ];

    /**
     * Scope: only active coupons.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    /**
     * Whether this coupon may still be applied to an order worth $subtotal.
     */
    public function isValidFor(float $subtotal): bool
    {
        if (! $this->active) {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        if ($this->usage_limit !== null && $this->used_count >= $this->usage_limit) {
            return false;
        }

        if ($this->min_order_amount !== null && $subtotal < (float) $this->min_order_amount) {
            return false;
        }

        return true;
    }

    /**
     * Discount amount for a given subtotal. A fixed discount is capped at the
     * subtotal so it can never make the total negative.
     */
    public function discountFor(float $subtotal): float
    {
        if (! $this->isValidFor($subtotal)) {
            return 0.0;
        }

        if ($this->type === self::PERCENT) {
            return round($subtotal * ($this->value / 100), 2);
        }

        return (float) min((float) $this->value, $subtotal);
    }
}
