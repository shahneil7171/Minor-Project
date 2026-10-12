<?php

namespace App\Models;

use App\Support\FulfilmentOwner;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderItem extends Model
{
    use HasFactory;

    /**
     * Order lines the ADMIN STORE is responsible for preparing.
     *
     * Matches the resolver in App\Support\FulfilmentOwner: an explicit
     * 'store' marker, or a null seller_id snapshot (the platform-owned
     * convention this schema has always used).
     */
    public function scopeStoreOwned(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->where('owner_type', FulfilmentOwner::STORE)
                ->orWhere(function (Builder $inner) {
                    $inner->whereNull('owner_type')->whereNull('seller_id');
                });
        });
    }

    /**
     * Order lines a REGISTERED SELLER is responsible for preparing.
     */
    public function scopeSellerOwned(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->where('owner_type', FulfilmentOwner::SELLER)
                ->orWhere(function (Builder $inner) {
                    $inner->whereNull('owner_type')->whereNotNull('seller_id');
                });
        });
    }

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
        'owner_type',
        'seller_ready_at',
        'inventory_released_at',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'price' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'quantity' => 'integer',
        'seller_ready_at' => 'datetime',
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
     * Whether the line's responsible owner has finished packing it.
     *
     * This is true for BOTH fulfilment owners: a seller packing their own
     * lines and the Admin store packing platform-owned lines both stamp the
     * same `seller_ready_at` column. That is deliberate — the multi-seller
     * readiness system is reused rather than duplicated, so one column tracks
     * "this line is packed" no matter who packed it.
     */
    public function isFulfilmentReady(): bool
    {
        return $this->seller_ready_at !== null;
    }

    /**
     * Whether the SELLER has finished packing this line (multi-seller
     * readiness — stamped by the owning seller's "Mark Ready for Pickup").
     *
     * Kept as the seller-specific alias of isFulfilmentReady() so existing
     * seller-side callers keep their exact meaning.
     */
    public function isSellerReady(): bool
    {
        return $this->isFulfilmentReady();
    }

    /**
     * The seller account responsible for this line (null for admin/seed).
     */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    /**
     * Who is responsible for preparing this line: 'store' (the Admin store),
     * 'seller' or 'unknown' (legacy ownership that needs review).
     *
     * Centralised in App\Support\FulfilmentOwner so every surface — customer
     * order page, admin orders, admin store fulfilment, seller orders,
     * delivery pages and notifications — resolves ownership identically.
     */
    public function fulfilmentOwnerType(): string
    {
        return FulfilmentOwner::of($this);
    }

    /**
     * Whether the Admin store prepares this line (platform-owned product).
     */
    public function isStoreOwned(): bool
    {
        return FulfilmentOwner::isStore($this);
    }

    /**
     * Whether a registered seller prepares this line.
     */
    public function isSellerOwned(): bool
    {
        return FulfilmentOwner::isSeller($this);
    }

    /**
     * Whether this legacy line's owner could not be established.
     */
    public function isUnknownOwnership(): bool
    {
        return FulfilmentOwner::isUnknown($this);
    }

    /**
     * Readable name of the owner preparing this line ("KDP MART Store",
     * the seller's name, or "Unassigned").
     */
    public function fulfilmentOwnerName(): string
    {
        return FulfilmentOwner::ownerName($this);
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
