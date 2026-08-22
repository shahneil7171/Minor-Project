<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A customer's persistent cart. One cart per user; lines live in cart_items
 * and survive logout because they are keyed by user_id, not session id.
 */
class Cart extends Model
{
    protected $fillable = ['user_id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    /**
     * The cart lines in the same shape the storefront has always used
     * (array keyed by variant-aware cart key), so existing views and
     * checkout logic keep working unchanged.
     *
     * @return array<string, array<string, mixed>>
     */
    public function toLines(): array
    {
        $lines = [];

        foreach ($this->items()->orderBy('id')->get() as $item) {
            $lines[$item->product_key] = [
                'product'         => $item->product_slug,
                'title'           => $item->title,
                'image'           => $item->image,
                'price'           => (float) $item->price,
                'quantity'        => (int) $item->quantity,
                'selected_options' => $item->selected_options ?? [],
                'options_text'    => (string) ($item->options_text ?? ''),
                'sku'             => $item->sku,
                'variant_id'      => $item->variant_id,
            ];
        }

        return $lines;
    }
}
