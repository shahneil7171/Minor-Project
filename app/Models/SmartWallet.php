<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SmartWallet extends Model
{
    protected $fillable = ['user_id','balance','total_earned','total_spent','total_refund_credited'];

    protected $casts = [
        'balance' => 'decimal:2',
        'total_earned' => 'decimal:2',
        'total_spent' => 'decimal:2',
        'total_refund_credited' => 'decimal:2',
    ];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function transactions(): HasMany { return $this->hasMany(SmartCoinTransaction::class, 'wallet_id'); }

    public function floatBalance(): float { return (float) $this->balance; }
}
