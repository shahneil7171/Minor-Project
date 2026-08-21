<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReturnRequest extends Model
{
    protected $table = 'returns';

    protected $fillable = [
        'order_id',
        'user_id',
        'order_number',
        'customer_email',
        'product_slug',
        'product_title',
        'reason',
        'status',
        'admin_note',
    ];

    public const STATUSES = ['requested', 'approved', 'rejected', 'completed'];

    public const STATUS_LABELS = [
        'requested' => 'Requested',
        'approved'  => 'Approved',
        'rejected'  => 'Rejected',
        'completed' => 'Completed',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function scopeStatus($query, ?string $status)
    {
        if ($status !== null && in_array($status, self::STATUSES, true)) {
            $query->where('status', $status);
        }

        return $query;
    }
}
