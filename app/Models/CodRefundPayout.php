<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CodRefundPayout extends Model
{
    protected $fillable = ['refund_id','return_request_id','delivery_partner_id','assigned_by','amount','status','confirmation_evidence','confirmed_at','confirmed_by','note'];

    protected $casts = ['amount' => 'decimal:2', 'confirmed_at' => 'datetime'];

    public const STATUSES = ['assigned','in_progress','confirmed','failed'];

    public function refund(): BelongsTo { return $this->belongsTo(Refund::class); }
    public function returnRequest(): BelongsTo { return $this->belongsTo(ReturnRequest::class, 'return_request_id'); }
    public function deliveryPartner(): BelongsTo { return $this->belongsTo(User::class, 'delivery_partner_id'); }
    public function confirmer(): BelongsTo { return $this->belongsTo(User::class, 'confirmed_by'); }
}
