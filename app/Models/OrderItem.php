<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderItem extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'order_id',
        'product_slug',
        'product_id',
        'variant_id',
        'product_title',
        'product_image',
        'sku',
        'price',
        'quantity',
        'subtotal',
        'options_text',
        'seller_id',
        'inventory_released_at',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'price' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'quantity' => 'integer',
        'inventory_released_at' => 'datetime',
    ];

    /**
     * The order this item belongs to.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Every inventory movement caused by this order line (sale, and the
     * matching cancellation / return restock when they happen).
     */
    public function inventoryTransactions(): HasMany
    {
        return $this->hasMany(InventoryTransaction::class);
    }

    /**
     * Whether this line's stock has already been given back to inventory.
     * The guard that makes a cancellation restore happen exactly once.
     */
    public function inventoryReleased(): bool
    {
        return $this->inventory_released_at !== null;
    }

    /**
     * The seller account responsible for this line (null for admin/seed).
     */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    /**
     * Every return request raised against this order line.
     */
    public function returnRequests(): HasMany
    {
        return $this->hasMany(ReturnRequest::class);
    }

    /**
     * The most recent return request for this line (drives the order page UI).
     */
    public function latestReturnRequest(): ?ReturnRequest
    {
        return $this->returnRequests()->latest('id')->first();
    }

    /**
     * Quantity already refunded to the buyer for this line.
     */
    public function refundedQuantity(): int
    {
        return (int) $this->returnRequests()
            ->where('refund_status', 'refunded')
            ->sum('quantity');
    }

    /**
     * Quantity currently locked by return requests that are still in flight
     * (pending review, approved, picked up, refund processing, ...).
     */
    public function lockedReturnQuantity(): int
    {
        return (int) $this->returnRequests()
            ->whereIn('status', ReturnRequest::LOCKING_STATUSES)
            ->sum('quantity');
    }

    /**
     * How many units of this line may still be returned.
     *
     * Purchased 3, one request for 2 in flight => 1 still returnable.
     */
    public function returnableQuantity(): int
    {
        return max(0, (int) $this->quantity - $this->lockedReturnQuantity());
    }

    /**
     * Whether a return request for this line is still being processed.
     */
    public function hasActiveReturnRequest(): bool
    {
        return $this->returnRequests()
            ->whereIn('status', ReturnRequest::LOCKING_STATUSES)
            ->exists();
    }
}
