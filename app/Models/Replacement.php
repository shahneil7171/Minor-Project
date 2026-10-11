<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Replacement extends Model
{
    protected $fillable = ['return_request_id','order_id','order_item_id','user_id','product_slug','variant_id','quantity','price_difference','payment_status','status','idempotency_key','note','shipped_at','delivered_at'];

    protected $casts = ['price_difference' => 'decimal:2', 'shipped_at' => 'datetime', 'delivered_at' => 'datetime'];

    public const STATUSES = ['requested','approved','rejected','processing','shipped','delivered','cancelled'];

    public function returnRequest(): BelongsTo { return $this->belongsTo(ReturnRequest::class, 'return_request_id'); }
    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
    public function orderItem(): BelongsTo { return $this->belongsTo(OrderItem::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
