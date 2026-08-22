<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartItem extends Model
{
    protected $fillable = [
        'cart_id',
        'product_key',
        'product_slug',
        'title',
        'image',
        'price',
        'quantity',
        'selected_options',
        'options_text',
        'sku',
        'variant_id',
    ];

    protected $casts = [
        'price'           => 'float',
        'quantity'        => 'integer',
        'selected_options' => 'array',
    ];

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }
}
