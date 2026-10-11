<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Refund extends Model
{
    protected $fillable = ['return_request_id','order_id','order_item_id','user_id','amount','currency_code','method','provider','status','provider_reference','idempotency_key','note','completed_at'];

    protected $casts = ['amount' => 'decimal:2', 'completed_at' => 'datetime'];

    public const STATUSES = ['pending','processing','completed','failed'];
    public const METHODS = ['smart_coins','original_simulated','cod_cash'];

    public function returnRequest(): BelongsTo { return $this->belongsTo(ReturnRequest::class, 'return_request_id'); }
    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
    public function orderItem(): BelongsTo { return $this->belongsTo(OrderItem::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function payout(): HasOne { return $this->hasOne(CodRefundPayout::class, 'refund_id'); }

    public function isCompleted(): bool { return $this->status === 'completed'; }
}
