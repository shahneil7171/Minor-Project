<?php

use App\Models\Category;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Database-backed product catalog.
 *
 * Creates the `products` table with a real foreign key to `categories`
 * (`products.category_id` = the single source of truth for organisation)
 * and safely backfills the existing catalog into it:
 *
 * - Seed products from config/catalog.php are inserted with is_seed = true.
 * - Each product's previously selected category NAME is resolved against
 *   the categories table (exact match, case-insensitive). If that exact
 *   category does not exist yet it is created — the original selection is
 *   preserved verbatim, nothing is guessed.
 *
 * Nothing is deleted or reset: existing orders, users, carts, wishlist,
 * reviews and coupons keep referencing products by slug, which is unchanged.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 191)->unique();
            $table->string('title');
            $table->string('sku')->nullable();
            $table->string('subtitle')->nullable();
            $table->text('description')->nullable();
            $table->string('image')->nullable();
            $table->json('images')->nullable();
            $table->json('details')->nullable();
            $table->decimal('price', 10, 2)->default(0);
            $table->decimal('special_price', 10, 2)->nullable();
            $table->unsignedInteger('quantity')->default(0);
            $table->string('stock_status', 20)->default('in-stock');

            // The relationship: categories.id → products.category_id.
            $table->foreignId('category_id')
                ->nullable()
                ->constrained('categories')
                ->nullOnDelete();

            // Denormalised fallback label derived from the relationship.
            // Named category_name so it does not shadow the Product::category()
            // Eloquent relationship (which is the single source of truth via
            // products.category_id).
            $table->string('category_name')->nullable();

            $table->string('subcategory')->nullable();
            $table->string('brand')->nullable();
            $table->decimal('tax', 5, 2)->default(0);
            $table->boolean('status')->default(true);
            $table->json('tags')->nullable();
            $table->json('options')->nullable();
            $table->json('variants')->nullable();
            $table->boolean('is_seed')->default(false);
            $table->timestamps();

            $table->index('category_id');
            $table->index('status');
        });

        $this->backfillCatalog();
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }

    /**
     * Import the pre-existing catalog rows (seed config + legacy JSON store).
     */
    private function backfillCatalog(): void
    {
        foreach ($this->catalogRows() as $slug => $row) {
            $this->importRow($slug, $row, isSeed: true);
        }

        // Legacy admin-created products stored outside the database, if any.
        foreach ($this->legacyRows() as $slug => $row) {
            $this->importRow($slug, $row, isSeed: false);
        }
    }

    private function importRow(string $slug, array $row, bool $isSeed): void
    {
        if (\DB::table('products')->where('slug', $slug)->exists()) {
            return;
        }

        $special = $this->floatOf($row['special_price'] ?? 0);

        \DB::table('products')->insert([
            'slug'          => $slug,
            'title'         => (string) ($row['title'] ?? $slug),
            'sku'           => $row['sku'] ?? null,
            'subtitle'      => $row['subtitle'] ?? null,
            'description'   => (string) ($row['description'] ?? ''),
            'image'         => $row['image'] ?? null,
            'images'        => json_encode(array_values((array) ($row['images'] ?? []))),
            'details'       => json_encode(array_values((array) ($row['details'] ?? []))),
            'price'         => $this->floatOf($row['price'] ?? 0),
            'special_price' => $special > 0 ? $special : null,
            'quantity'      => (int) ($row['quantity'] ?? 0),
            'stock_status'  => (string) ($row['stock_status'] ?? 'in-stock'),
            'category_id'   => $this->ensureCategory($row['category'] ?? null),
            'category_name' => $row['category'] ?? null,
            'subcategory'   => $row['subcategory'] ?? null,
            'brand'         => $row['brand'] ?? null,
            'tax'           => $this->floatOf($row['tax'] ?? 0),
            'status'        => (int) ($row['status'] ?? 1) === 1,
            'tags'          => json_encode(array_values((array) ($row['tags'] ?? []))),
            'options'       => json_encode((array) ($row['options'] ?? [])),
            'variants'      => json_encode((array) ($row['variants'] ?? [])),
            'is_seed'       => $isSeed,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }

    /**
     * Seed catalog rows from configuration.
     *
     * @return iterable<string, array>
     */
    private function catalogRows(): iterable
    {
        foreach ((array) config('catalog.seed_products', []) as $slug => $row) {
            yield (string) $slug => (array) $row;
        }
    }

    /**
     * Legacy JSON-store rows (custom_products.json), if any exist.
     *
     * @return array<string, array>
     */
    private function legacyRows(): array
    {
        try {
            $file = storage_path('app/custom_products.json');

            if (! is_file($file)) {
                return [];
            }

            $decoded = json_decode((string) file_get_contents($file), true);

            return is_array($decoded) ? $decoded : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Resolve a previously selected category to a real category id.
     *
     * Accepts the category id or its exact name (legacy records). When only
     * a name is known and no matching category exists, the category is
     * created — the original selection is preserved verbatim, never guessed
     * from the product title/description/brand/SKU/images.
     */
    private function ensureCategory(mixed $identifier): ?int
    {
        if ($identifier === null) {
            return null;
        }

        if (is_numeric($identifier)) {
            $found = Category::find((int) $identifier);

            if ($found) {
                return $found->id;
            }
        }

        $name = trim((string) $identifier);

        if ($name === '') {
            return null;
        }

        $existing = Category::query()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->first();

        if ($existing) {
            return $existing->id;
        }

        $baseSlug = \Illuminate\Support\Str::slug($name) ?: 'category';
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

    private function floatOf(mixed $value): float
    {
        return (float) filter_var((string) ($value ?? 0), FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    }
};
