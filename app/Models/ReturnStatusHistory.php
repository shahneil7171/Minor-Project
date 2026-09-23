<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One entry in a return request's status audit trail.
 *
 * Rows are only ever written by App\Services\ReturnService — never directly
 * from a controller — so every real return status change is recorded exactly
 * once with its actor and timestamp. Kept deliberately separate from the
 * Phase 1 order_status_histories table (order history vs return history).
 */
class ReturnStatusHistory extends Model
{
    protected $table = 'return_status_histories';

    protected $fillable = [
        'return_request_id',
        'from_status',
        'to_status',
        'changed_by',
        'note',
    ];

    public function returnRequest(): BelongsTo
    {
        return $this->belongsTo(ReturnRequest::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    public function statusLabel(): string
    {
        return ReturnRequest::STATUS_LABELS[$this->to_status]
            ?? ReturnRequest::STATUS_ALIASES[$this->to_status]
            ?? ucfirst((string) $this->to_status);
    }

    public function actorLabel(): string
    {
        return $this->actor?->name ?? 'System';
    }
}
