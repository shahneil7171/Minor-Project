<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A catalog product stored in the database.
 *
 * `category_id` is the single source of truth for product organisation;
 * the `category` column is a denormalised display label derived from the
 * relationship (never the other way around).
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
}
