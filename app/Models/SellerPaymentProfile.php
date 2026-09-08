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
    /**
     * Preferred payout methods a seller may select.
     */
    public const PAYMENT_METHODS = [
        'upi',
        'bank_transfer',
        'both',
    ];

    public const PAYMENT_METHOD_LABELS = [
        'upi'            => 'UPI',
        'bank_transfer' => 'Bank Transfer',
        'both'          => 'Both (UPI + Bank Transfer)',
    ];

    /**
     * Admin-driven verification lifecycle:
     *  - not_configured   nothing stored yet
     *  - configured        seller has stored at least one payout destination
     *  - pending_verification admin flagged for review
     *  - verified          admin manually confirmed (college-project flow)
     *  - rejected          admin declined with a rejection reason
     *  - request_update     admin asked the seller to fix/refresh details
     */
    public const PAYMENT_STATUSES = [
        'not_configured',
        'configured',
        'pending_verification',
        'verified',
        'rejected',
        'request_update',
    ];

    public const PAYMENT_STATUS_LABELS = [
        'not_configured'     => 'Not Configured',
        'configured'        => 'Configured',
        'pending_verification' => 'Pending Verification',
        'verified'          => 'Verified',
        'rejected'          => 'Rejected',
        'request_update'     => 'Update Requested',
    ];

    /**
     * Bank account types (optional).
     */
    public const ACCOUNT_TYPES = [
        'savings',
        'current',
    ];

    public const ACCOUNT_TYPE_LABELS = [
        'savings' => 'Savings',
        'current'  => 'Current',
    ];

    protected $fillable = [
        'seller_id',
        'upi_id',
        'mobile_number',
        'payment_email',
        'qr_code_path',
        'account_holder_name',
        'bank_name',
        'branch_name',
        'account_number',
        'ifsc_code',
        'account_type',
        'payment_method',
        'is_active',
        'payment_status',
        'admin_note',
        'rejection_reason',
        'verified_at',
    ];

    protected $casts = [
        'is_active'      => 'boolean',
        'account_number' => 'encrypted',
        'verified_at'    => 'datetime',
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
     * Whether the profile holds at least one real payout destination.
     */
    public function hasAnyPaymentInfo(): bool
    {
        return $this->upi_id
            || $this->qr_code_path
            || $this->account_number
            || in_array($this->payment_method, ['bank_transfer', 'both'], true);
    }

    /**
     * Human-readable label for the preferred payment method.
     */
    public function paymentMethodLabel(): string
    {
        return self::PAYMENT_METHOD_LABELS[$this->payment_method] ?? 'Not selected';
    }

    /**
     * Human-readable label for the payment verification status.
     */
    public function paymentStatusLabel(): string
    {
        return self::PAYMENT_STATUS_LABELS[$this->payment_status] ?? ucfirst((string) $this->payment_status);
    }

    /**
     * Human-readable label for the bank account type.
     */
    public function accountTypeLabel(): string
    {
        return self::ACCOUNT_TYPE_LABELS[$this->account_type] ?? '—';
    }

    /**
     * Whether the admin has manually verified this payout profile.
     */
    public function isVerified(): bool
    {
        return $this->payment_status === 'verified';
    }

    /**
     * Bootstrap CSS badge colour per verification status.
     */
    public function paymentStatusBadge(): string
    {
        return match ($this->payment_status) {
            'verified'          => 'bg-success',
            'pending_verification' => 'bg-warning text-dark',
            'rejected'          => 'bg-danger',
            'request_update'     => 'bg-info text-dark',
            'configured'        => 'bg-primary',
            default             => 'bg-secondary',
        };
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
