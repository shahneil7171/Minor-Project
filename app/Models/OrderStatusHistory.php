<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One entry in an order's status audit trail.
 *
 * Rows are only ever written by App\Services\OrderStatusService — never
 * directly from a controller — so every real status change is recorded
 * exactly once with its actor and timestamp.
 */
class OrderStatusHistory extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'order_id',
        'from_status',
        'to_status',
        'changed_by',
        'note',
    ];

    /**
     * The order this entry belongs to.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * The user that caused the transition (null for system transitions).
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    /**
     * Human-readable label for the resulting status.
     */
    public function statusLabel(): string
    {
        return Order::STATUS_LABELS[$this->to_status] ?? ucfirst((string) $this->to_status);
    }

    /**
     * Human-readable label for the status the order came from.
     */
    public function fromStatusLabel(): ?string
    {
        if ($this->from_status === null) {
            return null;
        }

        return Order::STATUS_LABELS[$this->from_status] ?? ucfirst((string) $this->from_status);
    }

    /**
     * How the actor should be described in the UI (falls back to "System").
     */
    public function actorLabel(): string
    {
        return $this->actor?->name ?? 'System';
    }

    /**
     * The actor's role label (Admin / Seller / Delivery Partner / Buyer).
     */
    public function actorRoleLabel(): ?string
    {
        return $this->actor?->accountTypeLabel();
    }
}
