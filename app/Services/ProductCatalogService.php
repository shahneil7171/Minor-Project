<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Product;

/**
 * ProductCatalogService
 *
 * Single access point for the product catalog shared by the storefront and
 * the product management routes.
 *
 * The catalog lives in the database `products` table. `category_id` (a real
 * foreign key to `categories`) is the single source of truth for product
 * organisation; the `category` string is a denormalised display label that
 * is always derived from the relationship.
 *
 * The service keeps the slug-keyed array API the storefront has always used
 * so views/controllers keep working unchanged.
 */
class ProductCatalogService
{
    /**
     * Per-request memo of the catalog (the service is an app singleton).
     */
    private ?array $memo = null;

    /**
     * The built-in seed catalog shipped with the store (kept for reference).
     */
    public function seedProducts(): array
    {
        return config('catalog.seed_products', []);
    }

    /**
     * Admin/seller created products (is_seed = false), keyed by slug.
     */
    public function customProducts(): array
    {
        return Product::query()
            ->with('category')
            ->where('is_seed', false)
            ->orderBy('id')
            ->get()
            ->map(fn (Product $p) => $this->toShape($p))
            ->keyBy(fn (array $p) => $p['slug'])
            ->all();
    }

    /**
     * Persist catalog rows (upsert by slug). Kept for backwards
     * compatibility with the previous JSON-store API.
     */
    public function saveCustomProducts(array $rows): void
    {
        foreach ($rows as $slug => $row) {
            $row['slug'] = $row['slug'] ?? $slug;
            $this->upsertRow((string) $slug, $row);
        }

        $this->flush();
    }

    /**
     * The complete catalog, keyed by slug (insertion order preserved).
     */
    public function all(): array
    {
        if ($this->memo !== null) {
            return $this->memo;
        }

        return $this->memo = Product::query()
            ->with('category')
            ->orderBy('id')
            ->get()
            ->map(fn (Product $p) => $this->toShape($p))
            ->keyBy(fn (array $p) => $p['slug'])
            ->all();
    }

    /**
     * Whether a product with the given slug exists in the catalog.
     */
    public function exists(string $slug): bool
    {
        return Product::where('slug', $slug)->exists();
    }

    /**
     * Find a single product by slug, or null when missing.
     */
    public function find(string $slug): ?array
    {
        $product = Product::findBySlug($slug);

        return $product ? $this->toShape($product) : null;
    }

    /**
     * Create or update a catalog row from validated form data.
     */
    public function upsertRow(string $slug, array $row): Product
    {
        $product = Product::firstOrNew(['slug' => $slug]);

        $product->fill([
            'title'         => (string) ($row['title'] ?? $slug),
            'sku'           => $row['sku'] ?? null,
            'subtitle'      => $row['subtitle'] ?? null,
            'description'   => (string) ($row['description'] ?? ''),
            'image'         => $row['image'] ?? null,
            'images'        => array_values((array) ($row['images'] ?? [])),
            'details'       => array_values((array) ($row['details'] ?? [])),
            'price'         => (float) ($row['price'] ?? 0),
            'special_price' => isset($row['special_price']) && $row['special_price'] !== '' && $row['special_price'] !== null
                                ? (float) $row['special_price'] : null,
            'quantity'      => (int) ($row['quantity'] ?? 0),
            'stock_status'  => (string) ($row['stock_status'] ?? 'in-stock'),
            'category_id'   => $row['category_id'] ?? null,
            'category_name' => $row['category_name'] ?? null,
            'subcategory'   => $row['subcategory'] ?? null,
            'brand'         => $row['brand'] ?? null,
            'tax'           => (float) ($row['tax'] ?? 0),
            'status'        => (int) ($row['status'] ?? 1),
            'tags'          => array_values((array) ($row['tags'] ?? [])),
            'options'       => array_values((array) ($row['options'] ?? [])),
            'variants'      => array_values((array) ($row['variants'] ?? [])),
        ]);

        if (! $product->exists) {
            $product->is_seed = false;
        }

        $product->save();
        $this->flush();

        return $product;
    }

    /**
     * Delete a catalog product by slug (if present).
     */
    public function deleteRow(string $slug): bool
    {
        $deleted = (bool) Product::where('slug', $slug)->delete();
        $this->flush();

        return $deleted;
    }

    /**
     * Clear the per-request memo (call after out-of-band writes).
     */
    public function flush(): void
    {
        $this->memo = null;
    }

    /**
     * Resolve a database Category from a mixed identifier.
     *
     * Accepts the category id, slug, or exact name and always returns the
     * actual database record (or null). This is the ONLY way product-to-
     * category assignment is resolved - the category is never guessed from
     * the product title/description/SKU/images.
     */
    public function resolveCategory(int|string|null $identifier): ?Category
    {
        $identifier = trim((string) ($identifier ?? ''));

        if ($identifier === '') {
            return null;
        }

        if (ctype_digit($identifier)) {
            $category = Category::find((int) $identifier);

            if ($category) {
                return $category;
            }
        }

        return Category::where('slug', mb_strtolower($identifier))
            ->orWhereRaw('LOWER(name) = ?', [mb_strtolower($identifier)])
            ->first();
    }
    /**
     * Whether a product belongs to the given database category.
     *
     * The relationship (category_id) decides; the denormalised name is only
     * a legacy fallback for records that predate the foreign key.
     */
    public function productBelongsToCategory(array $product, Category $category, bool $includeChildren = false): bool
    {
        $categoryIds = [$category->id];

        if ($includeChildren) {
            foreach ($category->children as $child) {
                $categoryIds[] = $child->id;
            }
        }

        if (isset($product['category_id']) && $product['category_id'] !== null && $product['category_id'] !== '') {
            return in_array((int) $product['category_id'], array_map('intval', $categoryIds), true);
        }

        // Legacy fallback: exact stored-name match against the DB category names.
        $names = array_map(
            fn (Category $c) => mb_strtolower(trim($c->name)),
            $includeChildren ? $category->children->push($category)->all() : [$category]
        );

        return in_array(mb_strtolower(trim((string) ($product['category'] ?? ''))), $names, true);
    }

    /**
     * All enabled catalog products belonging to the given database category,
     * keyed by slug. Parent categories include their child categories'
     * products (e.g. Electronics also returns Mobiles/Laptops/Accessories).
     *
     * @return array<string, array<string, mixed>>
     */
    public function productsForCategory(Category $category): array
    {
        $categoryIds = [$category->id];

        foreach ($category->children as $child) {
            $categoryIds[] = $child->id;
        }

        return Product::query()
            ->with('category')
            ->enabled()
            ->whereIn('category_id', $categoryIds)
            ->orderBy('id')
            ->get()
            ->map(fn (Product $p) => $this->toShape($p))
            ->keyBy(fn (array $p) => $p['slug'])
            ->all();
    }

    /**
     * Whether a catalog product is enabled (OpenCart-style status flag).
     */
    public function isProductEnabled(array $product): bool
    {
        return ! isset($product['status']) || (int) $product['status'] === 1;
    }

    /**
     * Convert a Product model into the legacy array shape used across the
     * storefront views/controllers.
     */
    private function toShape(Product $product): array
    {
        return [
            'title'         => (string) $product->title,
            'sku'           => $product->sku,
            'subtitle'      => $product->subtitle,
            'description'   => (string) $product->description,
            'image'         => $product->image,
            'images'        => $product->images ?? [],
            'details'       => $product->details ?? [],
            'price'         => (float) $product->price,
            'special_price' => $product->special_price,
            'quantity'      => (int) $product->quantity,
            'stock_status'  => (string) $product->stock_status,
            'category'      => $product->category?->name ?? $product->category_name,
            'category_id'   => $product->category_id,
            'category_slug' => $product->category?->slug,
            'subcategory'   => $product->subcategory,
            'brand'         => $product->brand,
            'tax'           => (float) $product->tax,
            'status'        => (int) $product->status,
            'slug'          => (string) $product->slug,
            'tags'          => $product->tags ?? [],
            'options'       => $product->options ?? [],
            'variants'      => $product->variants ?? [],
        ];
    }
}