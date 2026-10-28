<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A buyer's saved payment method (UPI ID, or safe card display metadata).
 *
 * Security notes:
 * - Belongs to the authenticated buyer only; ownership is enforced in the
 *   controller on every read/update/delete.
 * - Raw card numbers and CVVs are NEVER stored. Only safe display metadata
 *   (brand, masked last four, expiry) and a gateway token/reference may be
 *   stored. The configured gateways (Razorpay/Cashfree) do not currently
 *   expose card tokenization, so the gateway_token column is reserved for
 *   future support.
 * - UPI PINs, banking passwords, and OTPs are never stored.
 */
class BuyerPaymentMethod extends Model
{
    public const TYPES = ['upi', 'card', 'netbanking'];

    public const TYPE_LABELS = [
        'upi'        => 'UPI',
        'card'       => 'Card',
        'netbanking' => 'Net Banking',
    ];

    protected $fillable = [
        'user_id',
        'type',
        'label',
        'upi_id',
        'card_brand',
        'card_last4',
        'card_expiry_month',
        'card_expiry_year',
        'gateway_provider',
        'gateway_token',
        'bank_code',
        'is_default',
    ];

    protected $casts = [
        'is_default'         => 'boolean',
        'card_expiry_month'  => 'integer',
        'card_expiry_year'   => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function typeLabel(): string
    {
        return self::TYPE_LABELS[$this->type] ?? ucfirst((string) $this->type);
    }

    /**
     * Safe display string for the buyer (never includes sensitive data).
     */
    public function displayLabel(): string
    {
        if ($this->type === 'upi') {
            return $this->upi_id ?? 'UPI';
        }

        if ($this->type === 'card') {
            $brand = $this->card_brand ?? 'Card';
            $last4 = $this->card_last4 ? '****' . $this->card_last4 : '';
            $expiry = ($this->card_expiry_month && $this->card_expiry_year)
                ? sprintf('%02d/%d', $this->card_expiry_month, $this->card_expiry_year % 100)
                : '';

            return trim($brand . ' ' . $last4 . ' ' . $expiry);
        }

        if ($this->type === 'netbanking') {
            return $this->bank_code ? 'Bank: ' . $this->bank_code : 'Net Banking';
        }

        return $this->label ?? $this->typeLabel();
    }
}
