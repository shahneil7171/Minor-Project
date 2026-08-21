<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductView extends Model
{
    protected $fillable = ['product_slug', 'title', 'views'];

    /**
     * Increment the view counter for a product slug.
     *
     * Failures are swallowed so storefront rendering can never break
     * because of analytics.
     */
    public static function recordView(string $slug, ?string $title = null): void
    {
        try {
            $record = static::firstOrNew(['product_slug' => $slug]);
            $record->title = $record->title ?: $title;
            $record->views = ($record->views ?? 0) + 1;
            $record->save();
        } catch (\Throwable) {
            // Analytics must never break the store.
        }
    }
}
