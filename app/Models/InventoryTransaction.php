<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One audited inventory change (PHASE 3).
 *
 * Every write that moves stock goes through App\Services\InventoryService,
 * which records exactly one row per change with the previous and new stock
 * level of the affected inventory unit (the product row, or the variant
 * inside the product row's `variants` JSON).
 *
 * `quantity` is signed: negative when stock left the inventory (a sale),
 * positive when it came back (cancellation, return restock, adjustment).
 */
class InventoryTransaction extends Model
{
    /**
     * The complete, closed set of inventory transaction types.
     */
    public const TYPES = [
        'initial_stock',
        'sale',
        'cancellation',
        'return_restock',
        'manual_adjustment',
        'correction',
    ];

    /**
     * Human labels for the UI (admin / seller inventory history).
     */
    public const TYPE_LABELS = [
        'initial_stock'      => 'Initial stock',
        'sale'               => 'Sale',
        'cancellation'       => 'Cancellation',
        'return_restock'     => 'Return restock',
        'manual_adjustment'  => 'Manual adjustment',
        'correction'         => 'Correction',
    ];

    protected $fillable = [
        'product_id',
        'product_variant_id',
        'seller_id',
        'order_id',
        'order_item_id',
        'return_request_id',
        'type',
        'quantity',
        'previous_stock',
        'new_stock',
        'reason',
        'reference',
        'actor_id',
    ];

    protected $casts = [
        'quantity'       => 'integer',
        'previous_stock' => 'integer',
        'new_stock'      => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function returnRequest(): BelongsTo
    {
        return $this->belongsTo(ReturnRequest::class);
    }

    public function typeLabel(): string
    {
        return self::TYPE_LABELS[$this->type] ?? ucfirst((string) $this->type);
    }

    /**
     * Signed delta rendered for humans ("-2" / "+5").
     */
    public function signedQuantity(): string
    {
        return $this->quantity > 0 ? '+' . $this->quantity : (string) $this->quantity;
    }

    /**
     * Scope: only one inventory transaction type.
     */
    public function scopeType($query, ?string $type)
    {
        if ($type !== null && $type !== '' && in_array($type, self::TYPES, true)) {
            $query->where('type', $type);
        }

        return $query;
    }

    /**
     * Scope: inventory owned by a single seller.
     */
    public function scopeForSeller($query, int $sellerId)
    {
        return $query->where('seller_id', $sellerId);
    }
}