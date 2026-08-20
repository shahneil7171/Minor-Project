<?php

namespace App\Services;

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
}
