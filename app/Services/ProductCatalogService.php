<?php

namespace App\Services;

use App\Models\Category;
use Illuminate\Support\Facades\Storage;

/**
 * ProductCatalogService
 *
 * Single access point for the JSON-backed product catalog shared by the
 * public storefront and the authenticated product routes. The base seed
 * catalog lives in config/catalog.php and admin/seller added products are
 * stored in custom_products.json (custom_products_test.json while testing).
 */
class ProductCatalogService
{
    /**
     * File that stores admin/seller created products.
     */
    private function fileName(): string
    {
        return app()->environment('testing')
            ? 'custom_products_test.json'
            : 'custom_products.json';
    }

    /**
     * The built-in seed catalog shipped with the store.
     */
    public function seedProducts(): array
    {
        return config('catalog.seed_products', []);
    }

    /**
     * Admin/seller added products read from storage.
     */
    public function customProducts(): array
    {
        if (! Storage::disk('local')->exists($this->fileName())) {
            return [];
        }

        $json = Storage::disk('local')->get($this->fileName());
        if (! $json) {
            return [];
        }

        $data = json_decode($json, true);

        return is_array($data) ? $data : [];
    }

    /**
     * Persist the admin/seller added products.
     */
    public function saveCustomProducts(array $customProducts): void
    {
        Storage::disk('local')->put(
            $this->fileName(),
            json_encode($customProducts, JSON_PRETTY_PRINT)
        );
    }

    /**
     * The complete catalog: seed products merged with custom products.
     */
    public function all(): array
    {
        return array_merge($this->seedProducts(), $this->customProducts());
    }

    /**
     * Whether a product with the given slug exists in the catalog.
     */
    public function exists(string $slug): bool
    {
        return isset($this->all()[$slug]);
    }

    /**
     * Find a single product by slug, or null when missing.
     */
    public function find(string $slug): ?array
    {
        $products = $this->all();

        return $products[$slug] ?? null;
    }

    /**
     * Resolve a database Category from a mixed identifier.
     *
     * Accepts the category id, slug, or exact name and always returns the
     * actual database record (or null). This is the ONLY way product-to-
     * category assignment is resolved — the category is never guessed from
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
     * Whether a catalog product belongs to the given database category.
     *
     * Products created/edited through the admin forms store the real
     * relationship as `category_id`; seed products only carry the category
     * name, which is matched exactly against the database category name
     * (including its child categories for parent tiles).
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

        // Legacy products: exact stored-name match against the DB category names.
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
        return array_filter(
            $this->all(),
            fn (array $product) => $this->isProductEnabled($product)
                && $this->productBelongsToCategory($product, $category, true)
        );
    }

    /**
     * Whether a catalog product is enabled (OpenCart-style status flag).
     */
    public function isProductEnabled(array $product): bool
    {
        return ! isset($product['status']) || (int) $product['status'] === 1;
    }
}
