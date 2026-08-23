<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeds the baseline product catalog (config/catalog.php) into the
 * products table. Idempotent: rows are matched by slug, so re-running
 * never duplicates or overwrites admin edits.
 *
 * Each product's category is resolved against the categories table by
 * exact name (the same rule the backfill migration uses) — the selected
 * category is preserved verbatim, never guessed.
 */
class ProductsSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        foreach ((array) config('catalog.seed_products', []) as $slug => $row) {
            $row = (array) $row;
            $slug = (string) $slug;

            if (Product::where('slug', $slug)->exists()) {
                continue;
            }

            $special = isset($row['special_price']) && $row['special_price'] !== '' && $row['special_price'] !== null
                ? (float) $row['special_price'] : null;

            Product::create([
                'slug'          => $slug,
                'title'         => (string) ($row['title'] ?? $slug),
                'sku'           => $row['sku'] ?? strtoupper($slug),
                'subtitle'      => $row['subtitle'] ?? null,
                'description'   => (string) ($row['description'] ?? ''),
                'image'         => $row['image'] ?? null,
                'images'        => array_values((array) ($row['images'] ?? [])),
                'details'       => array_values((array) ($row['details'] ?? [])),
                'price'         => (float) ($row['price'] ?? 0),
                'special_price' => $special && $special > 0 ? $special : null,
                'quantity'      => (int) ($row['quantity'] ?? 0),
                'stock_status'  => (string) ($row['stock_status'] ?? 'in-stock'),
                'category_id'   => $this->ensureCategory($row['category'] ?? null),
                'category_name' => $row['category'] ?? null,
                'subcategory'   => $row['subcategory'] ?? null,
                'brand'         => $row['brand'] ?? null,
                'tax'           => (float) ($row['tax'] ?? 0),
                'status'        => (int) ($row['status'] ?? 1),
                'tags'          => array_values((array) ($row['tags'] ?? [])),
                'options'       => array_values((array) ($row['options'] ?? [])),
                'variants'      => array_values((array) ($row['variants'] ?? [])),
                'is_seed'       => true,
            ]);
        }
    }

    /**
     * Exact-name category resolution; creates the category when missing.
     */
    private function ensureCategory(?string $name): ?int
    {
        $name = trim((string) $name);

        if ($name === '') {
            return null;
        }

        $existing = Category::query()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->first();

        if ($existing) {
            return $existing->id;
        }

        $baseSlug = Str::slug($name) ?: 'category';
        $slug = $baseSlug;
        $counter = 1;

        while (Category::where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . $counter++;
        }

        $category = new Category();
        $category->name = $name;
        $category->slug = $slug;
        $category->sort_order = (int) (Category::max('sort_order') ?? 0) + 1;
        $category->is_active = true;
        $category->save();

        return $category->id;
    }
}
