<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A catalog product stored in the database.
 *
 * `category_id` is the single source of truth for product organisation;
 * the `category` column is a denormalised display label derived from the
 * relationship (never the other way around).
 *
 * INVENTORY (PHASE 3): `quantity` is the product-level STOCK (unchanged
 * column, reused as-is) and the `variants` JSON column holds the per-variant
 * stock. App\Services\InventoryService is the only place allowed to read or
 * write those numbers.
 */
class Product extends Model
{
    protected $fillable = [
        'slug',
        'title',
        'sku',
        'subtitle',
        'description',
        'image',
        'images',
        'details',
        'price',
        'special_price',
        'quantity',
        'reserved',
        'low_stock_threshold',
        'stock_status',
        'category_id',
        'category_name',
        'subcategory',
        'brand',
        'tax',
        'status',
        'tags',
        'options',
        'variants',
        'is_seed',
        'seller_id',
    ];

    protected $casts = [
        'images'        => 'array',
        'details'       => 'array',
        'tags'          => 'array',
        'options'       => 'array',
        'variants'      => 'array',
        'price'         => 'float',
        'special_price' => 'float',
        'tax'           => 'float',
        'quantity'      => 'integer',
        'reserved'      => 'integer',
        'low_stock_threshold' => 'integer',
        'status'        => 'integer',
        'is_seed'       => 'boolean',
    ];

    /**
     * The category this product belongs to.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * The seller account that manages this product (null for admin/seed).
     */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    /**
     * The full inventory history of this product (PHASE 3 audit trail).
     */
    public function inventoryTransactions(): HasMany
    {
        return $this->hasMany(InventoryTransaction::class);
    }

    /**
     * Enabled (visible to shoppers) products.
     */
    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('status', 1);
    }

    /**
     * Find a product by its slug (or null).
     */
    public static function findBySlug(string $slug): ?self
    {
        return static::where('slug', $slug)->first();
    }

    /**
     * Whether this product tracks its inventory per variant.
     */
    public function hasVariants(): bool
    {
        return ! empty($this->variants);
    }

    /**
     * The stable ids of every variant of this product.
     *
     * @return array<int, string>
     */
    public function variantIds(): array
    {
        return array_values(array_map(
            fn (array $variant) => (string) ($variant['id'] ?? ''),
            $this->variants ?? [],
        ));
    }
}
