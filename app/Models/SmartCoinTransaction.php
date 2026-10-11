<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SmartCoinTransaction extends Model
{
    protected $fillable = ['wallet_id','user_id','type','direction','amount','reference_type','reference_id','idempotency_key','description','actor_id','status','expires_at'];

    protected $casts = ['amount' => 'decimal:2', 'expires_at' => 'datetime'];

    public const TYPES = ['refund_credit','checkout_redemption','checkout_redemption_reversal','order_cancellation_restoration','admin_adjustment'];

    public function wallet(): BelongsTo { return $this->belongsTo(SmartWallet::class, 'wallet_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function actor(): BelongsTo { return $this->belongsTo(User::class, 'actor_id'); }
}
