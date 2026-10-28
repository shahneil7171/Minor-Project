<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A payment attempt for a simulated online payment method (UPI / Card).
 *
 * One order may have many attempts (a failed or expired attempt can be
 * retried, which creates a new attempt with a fresh five-minute deadline),
 * but only one attempt may succeed per order.
 *
 * Server time is the authority for the expiry deadline: `expires_at` is set
 * once at creation and is never reset by page refreshes or retries.
 */
class PaymentAttempt extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'order_id',
        'token',
        'payment_method',
        'currency',
        'amount',
        'status',
        'expires_at',
        'verified_at',
        'provider_reference',
        'failure_reason',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'expires_at' => 'datetime',
        'verified_at' => 'datetime',
    ];

    public const STATUS_PENDING = 'pending';
    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED = 'failed';
    public const STATUS_EXPIRED = 'expired';

    public const TERMINAL_STATUSES = [self::STATUS_SUCCESS, self::STATUS_FAILED, self::STATUS_EXPIRED];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Scope attempts belonging to the given account (payment ownership). */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeExpired($query)
    {
        return $query->where('status', self::STATUS_EXPIRED);
    }

    /** Whether the server-side deadline has passed. */
    public function isExpired(): bool
    {
        return $this->expires_at instanceof \Carbon\Carbon
            ? now()->greaterThan($this->expires_at)
            : false;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, self::TERMINAL_STATUSES, true);
    }

    /**
     * Mark the attempt as succeeded. Idempotent: returns false when the
     * attempt is already in a terminal state or has already expired — a late
     * simulated "success" after the deadline must never confirm the order.
     */
    public function markSuccess(string $providerReference): bool
    {
        if ($this->isTerminal()) {
            return false;
        }

        if ($this->isExpired()) {
            return $this->markExpired();
        }

        $this->forceFill([
            'status' => self::STATUS_SUCCESS,
            'provider_reference' => $providerReference,
            'verified_at' => now(),
        ])->save();

        return true;
    }

    /** Mark the attempt as failed. Idempotent while still pending. */
    public function markFailed(?string $reason = null): bool
    {
        if ($this->status !== self::STATUS_PENDING) {
            return false;
        }

        $this->forceFill([
            'status' => self::STATUS_FAILED,
            'failure_reason' => $reason,
        ])->save();

        return true;
    }

    /**
     * Mark the attempt as expired. Only transitions pending -> expired and
     * only when the deadline has already passed. Returns false for anything
     * else (idempotent, safe for retried webhooks / duplicate requests).
     */
    public function markExpired(): bool
    {
        if ($this->status !== self::STATUS_PENDING || !$this->isExpired()) {
            return false;
        }

        $this->forceFill(['status' => self::STATUS_EXPIRED])->save();

        return true;
    }

    /** Masked reference for display (last 6 characters only). */
    public function maskedProviderReference(): ?string
    {
        if (!$this->provider_reference) {
            return null;
        }

        $id = (string) $this->provider_reference;

        return strlen($id) <= 6
            ? str_repeat('•', strlen($id))
            : str_repeat('•', strlen($id) - 6).substr($id, -6);
    }
}
