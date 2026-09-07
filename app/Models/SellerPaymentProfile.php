<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A seller's payment profile: UPI ID, mobile number, QR code and optional
 * (private) bank details used to receive payments for the seller's products.
 *
 * Security notes:
 * - One profile per seller (unique `seller_id`).
 * - `account_number` is stored encrypted at rest (`encrypted` cast) and is
 *   never displayed in full outside the owning seller's settings page —
 *   always use maskedAccountNumber() for display.
 * - Only the owning seller (and admins) may view/manage a profile; the
 *   controller enforces this server-side, this model only holds data.
 */
class SellerPaymentProfile extends Model
{
    protected $fillable = [
        'seller_id',
        'upi_id',
        'mobile_number',
        'qr_code_path',
        'account_holder_name',
        'bank_name',
        'account_number',
        'ifsc_code',
        'is_active',
    ];

    protected $casts = [
        'is_active'      => 'boolean',
        'account_number' => 'encrypted',
    ];

    /**
     * The seller account that owns this payment profile.
     */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    /**
     * Masked bank account number for display, e.g. "XXXXXX1234".
     * The full value is never rendered outside the owner's settings page.
     */
    public function maskedAccountNumber(): ?string
    {
        $number = (string) $this->account_number;

        if ($number === '') {
            return null;
        }

        return 'XXXXXX' . substr($number, -4);
    }

    /**
     * Public-facing QR code URL (safe image path; paths are server-generated,
     * never taken from client input).
     */
    public function qrUrl(): ?string
    {
        if (! $this->qr_code_path) {
            return null;
        }

        return asset(ltrim($this->qr_code_path, '/'));
    }
}
