<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Services\ProductImageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Product image gallery behaviour.
 *
 * Guards against every duplication vector found in the original
 * implementation:
 *
 *  - the main photo being stored inside `images` AND rendered again,
 *  - equivalent references (`/uploads/a.webp`, `uploads/a.webp`,
 *    `http://<own-host>/uploads/a.webp`) counting as different images,
 *  - re-uploads of the same picture (byte-identical files under new
 *    timestamped names) accumulating visually duplicated thumbnails,
 *  - product creation/editing re-introducing duplicates.
 */
class ProductGalleryTest extends TestCase
{
    use RefreshDatabase;

    private const PNG_1PX = "\x89PNG\r\n\x1a\n\x00\x00\x00\rIHDR\x00\x00\x00\x01\x00\x00\x00\x01\x08\x06\x00\x00\x00\x1f\x15\xc4\x89\x00\x00\x00\nIDATx\x9cc\x00\x01\x00\x00\x05\x00\x01\r\n-\xb4\x00\x00\x00\x00IEND\xaeB`\x82";

    /** @var list<string> */
    private array $fixtures = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Product creation/editing in this suite files products under the
        // "Gadgets" category. Categories must now exist in the database and
        // pass server-side validation (created, active, top-level), so the
        // category itself is created once per test here.
        Category::firstOrCreate(
            ['slug' => 'gadgets'],
            ['name' => 'Gadgets', 'sort_order' => 1, 'is_active' => true]
        );
    }

    protected function tearDown(): void
    {
        foreach ($this->fixtures as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
        $this->fixtures = [];

        parent::tearDown();
    }

    /* ------------------------------------------------------------ helpers */

    private function seller(): User
    {
        return User::factory()->create(['account_type' => 'seller']);
    }

    private function buyer(): User
    {
        return User::factory()->create(['account_type' => 'buyer']);
    }

    private function createProduct(array $attributes = []): Product
    {
        return Product::create(array_merge([
            'slug'         => 'gallery-test-product',
            'title'        => 'Gallery Test Product',
            'sku'          => 'GTP-1',
            'subtitle'     => 'Subtitle',
            'description'  => 'Description',
            'image'        => '/uploads/products/main.webp',
            'images'       => [],
            'details'      => ['Detail'],
            'price'        => 199.99,
            'quantity'     => 5,
            'stock_status' => 'in-stock',
            'status'       => 1,
        ], $attributes));
    }

    /**
     * Create a local fixture file under public/uploads/products and return
     * its public reference. Files are removed again in tearDown(); existing
     * uploads are never touched.
     */
    private function fixture(string $name, string $bytes): string
    {
        $path = public_path('uploads/products/' . $name);
        file_put_contents($path, $bytes);
        $this->fixtures[] = $path;

        return '/uploads/products/' . $name;
    }

    /**
     * Extract the thumbnail sources (in render order) from a product-detail
     * HTML response.
     *
     * @return list<string>
     */
    private function thumbSources(string $html): array
    {
        if (! preg_match('/<div class="thumb-row">(.*?)<\/div>/s', $html, $row)) {
            return [];
        }

        preg_match_all('/src="([^"]+)"/', $row[1], $sources);

        return $sources[1];
    }

    private function detailHtml(Product|string $product): string
    {
        $slug = $product instanceof Product ? $product->slug : $product;

        return $this->actingAs($this->buyer())
            ->get(route('product.show', ['product' => $slug]))
            ->assertOk()
            ->getContent();
    }

    /**
     * How the reference appears inside an HTML attribute (Blade encodes &).
     */
    private function htmlAttr(string $url): string
    {
        return htmlspecialchars($url, ENT_QUOTES, 'UTF-8', false);
    }

    /* ------------------------------------------------------ normalisation */

    public function test_equivalent_references_normalize_to_the_same_key(): void
    {
        $bare       = ProductImageService::normalize('uploads/products/example.webp');
        $leading    = ProductImageService::normalize('/uploads/products/example.webp');
        $ownHostUrl = ProductImageService::normalize('http://localhost/uploads/products/example.webp');

        $this->assertSame('uploads/products/example.webp', $bare);
        $this->assertSame($bare, $leading);
        $this->assertSame($bare, $ownHostUrl);

        // Different files must never share an identity.
        $this->assertNotSame(
            ProductImageService::normalize('/uploads/products/a.webp'),
            ProductImageService::normalize('/uploads/products/b.webp')
        );
    }

    public function test_unique_list_preserves_order_and_ignores_empties(): void
    {
        $result = ProductImageService::uniqueList([
            '', null, '   ',
            '/uploads/products/a.webp',
            'uploads/products/a.webp',      // same file, different spelling
            '/uploads/products/b.webp',
            '',
        ]);

        $this->assertSame(
            ['/uploads/products/a.webp', '/uploads/products/b.webp'],
            $result
        );
    }

    /* --------------------------------------------------- display gallery */

    public function test_product_with_only_a_main_image_displays_one_image(): void
    {
        $product = $this->createProduct([
            'image'  => '/uploads/products/only-main.webp',
            'images' => [],
        ]);

        $html   = $this->detailHtml($product);
        $thumbs = $this->thumbSources($html);

        // Exactly ONE unique image: hero + its single thumbnail.
        $this->assertSame([asset('/uploads/products/only-main.webp')], $thumbs);
        $this->assertSame(2, substr_count($html, asset('/uploads/products/only-main.webp')));
        $this->assertStringContainsString('id="mainImage"', $html);
    }

    public function test_main_plus_different_additional_images_all_display(): void
    {
        $product = $this->createProduct([
            'image'  => '/uploads/products/front.webp',
            'images' => ['/uploads/products/back.webp', '/uploads/products/side.webp'],
        ]);

        $thumbs = $this->thumbSources($this->detailHtml($product));

        $this->assertSame([
            asset('/uploads/products/front.webp'),
            asset('/uploads/products/back.webp'),
            asset('/uploads/products/side.webp'),
        ], $thumbs);
    }

    public function test_main_image_duplicated_in_additional_images_displays_once(): void
    {
        // Exact database state from the bug report.
        $product = $this->createProduct([
            'image'  => '/uploads/products/s26.webp',
            'images' => [
                '/uploads/products/s26.webp',
                '/uploads/products/s26-side.webp',
            ],
        ]);

        $thumbs = $this->thumbSources($this->detailHtml($product));

        $this->assertSame([
            asset('/uploads/products/s26.webp'),
            asset('/uploads/products/s26-side.webp'),
        ], $thumbs);
    }

    public function test_equivalent_spellings_of_the_main_image_are_collapsed(): void
    {
        $product = $this->createProduct([
            'image'  => '/uploads/products/dup.webp',
            'images' => [
                'uploads/products/dup.webp',                          // no leading slash
                'http://localhost/uploads/products/dup.webp',         // own-host absolute URL
                '/uploads/products/other.webp',                       // genuinely different
            ],
        ]);

        $thumbs = $this->thumbSources($this->detailHtml($product));

        $this->assertSame([
            asset('/uploads/products/dup.webp'),
            asset('/uploads/products/other.webp'),
        ], $thumbs);
    }

    public function test_duplicate_additional_images_each_display_once(): void
    {
        $product = $this->createProduct([
            'image'  => '/uploads/products/main.webp',
            'images' => [
                '/uploads/products/a.webp',
                '/uploads/products/a.webp',
                'https://cdn.example.com/c.webp?auto=format&fit=crop&w=800&q=80',
                'https://cdn.example.com/c.webp?auto=format&fit=crop&w=800&q=80',
                '/uploads/products/b.webp',
            ],
        ]);

        $thumbs = $this->thumbSources($this->detailHtml($product));

        $this->assertSame([
            asset('/uploads/products/main.webp'),
            asset('/uploads/products/a.webp'),
            // Blade HTML-encodes & in attribute values.
            'https://cdn.example.com/c.webp?auto=format&amp;fit=crop&amp;w=800&amp;q=80',
            asset('/uploads/products/b.webp'),
        ], $thumbs);
    }

    public function test_empty_and_null_image_values_are_ignored(): void
    {
        // Create both products up-front: the catalog service memoizes per
        // request, so rows must exist before the first page hit.
        $product = $this->createProduct([
            'image'  => '',
            'images' => ['', null, '   ', '/uploads/products/real.webp'],
        ]);

        $empty = $this->createProduct([
            'slug' => 'no-images-product', 'image' => '', 'images' => [],
        ]);

        $html   = $this->detailHtml($product);
        $thumbs = $this->thumbSources($html);

        // After collapsing the empties a single-image gallery remains:
        // hero + its one thumbnail - and nothing else.
        $this->assertSame([asset('/uploads/products/real.webp')], $thumbs);
        $this->assertSame(2, substr_count($html, asset('/uploads/products/real.webp')));

        $emptyHtml = $this->detailHtml($empty);

        // Placeholder-only product: hero + its one thumbnail, nothing else.
        $this->assertSame(
            [$this->htmlAttr((string) asset(\App\Services\ProductImageService::defaultImage()))],
            $this->thumbSources($emptyHtml)
        );
    }

    public function test_different_images_are_never_removed(): void
    {
        // Real local files with DIFFERENT bytes - content hashing must keep all.
        $front = $this->fixture('t-front.webp', 'front-bytes');
        $back  = $this->fixture('t-back.webp', 'back-bytes');
        $side  = $this->fixture('t-side.webp', 'side-bytes');
        $cam   = $this->fixture('t-camera.webp', 'camera-bytes');

        $product = $this->createProduct([
            'image'  => $front,
            'images' => [$back, $side, $cam],
        ]);

        $thumbs = $this->thumbSources($this->detailHtml($product));

        $this->assertSame(
            [asset($front), asset($back), asset($side), asset($cam)],
            $thumbs
        );
    }

    public function test_legacy_products_with_byte_identical_reupload_copies_display_unique(): void
    {
        // Production data pattern: the same picture uploaded twice under two
        // timestamped filenames. Byte-identical content, different paths.
        $copyA = $this->fixture('legacy-copy-1.webp', str_repeat('SAME-IMAGE-BYTES-', 32));
        $copyB = $this->fixture('legacy-copy-2.webp', str_repeat('SAME-IMAGE-BYTES-', 32));
        $this->assertNotSame($copyA, $copyB);

        // Legacy storage shape: main image also stored inside `images[0]`.
        $product = $this->createProduct([
            'slug'   => 'legacy-dup-product',
            'image'  => $copyA,
            'images' => [$copyA, $copyB],
        ]);

        $html   = $this->detailHtml($product);
        $thumbs = $this->thumbSources($html);

        // All three references describe ONE picture: hero + its own thumb.
        $this->assertSame([asset($copyA)], $thumbs);
        $this->assertSame(2, substr_count($html, asset($copyA)));
    }

    public function test_seeded_products_render_a_duplicate_free_gallery(): void
    {
        // Seed rows ship with the main photo stored inside images[0]; the
        // fix must clean them up at display time without touching the DB.
        $seed     = (array) config('catalog.seed_products.smart-watch-pro', []);
        $expected = ProductImageService::uniqueGallery($seed['image'] ?? null, $seed['images'] ?? []);

        $this->assertNotEmpty($expected);

        $html   = $this->detailHtml('smart-watch-pro');
        $thumbs = $this->thumbSources($html);

        // Whatever the seed row stores, rendering is exactly the unique,
        // placeholder-policy-cleaned collection - hero plus its thumbnails.
        $this->assertSame(
            array_map(fn (string $ref) => $this->htmlAttr((string) asset($ref)), $expected),
            $thumbs
        );
    }

    /* ------------------------------------------------- phantom placeholder */

    public function test_seed_placeholder_is_never_rendered_as_a_second_image(): void
    {
        // THE reported bug (Signature Headphones): an admin-uploaded main
        // photo plus a generic seed-catalog placeholder lingering in the
        // stored gallery. The page must show exactly ONE image.
        $real        = $this->fixture('sh-real.webp', 'REAL-HEADPHONE-PHOTO-BYTES');
        $placeholder = config('catalog.seed_products.signature-headphones.image');

        $this->assertNotEmpty($placeholder);
        $this->assertTrue(ProductImageService::isPlaceholder((string) $placeholder));

        $product = $this->createProduct([
            'slug'   => 'phantom-placeholder-product',
            'image'  => $real,
            'images' => [$real, $placeholder], // legacy stored shape
        ]);

        $html   = $this->detailHtml($product);
        $thumbs = $this->thumbSources($html);

        // ONE unique image total: hero + its single thumbnail - and the
        // placeholder that no admin ever added appears NOWHERE.
        $this->assertSame([asset($real)], $thumbs);
        $this->assertSame(2, substr_count($html, asset($real)));
        $this->assertStringNotContainsString('unsplash', $html);
    }

    public function test_product_with_only_placeholder_images_shows_a_single_hero(): void
    {
        // A never-customized seed product legitimately shows its bootstrap
        // placeholder as the hero - once, with no invented companions.
        $placeholder = config('catalog.seed_products.smart-watch-pro.image');

        $product = $this->createProduct([
            'slug'   => 'only-placeholder-product',
            'image'  => $placeholder,
            'images' => [$placeholder],
        ]);

        $html   = $this->detailHtml($product);
        $thumbs = $this->thumbSources($html);

        // Placeholder-only product: hero + its one thumbnail, nothing else.
        $this->assertSame(
            [$this->htmlAttr((string) asset((string) $placeholder))],
            $thumbs
        );
    }

    public function test_arbitrary_admin_urls_are_never_treated_as_placeholders(): void
    {
        $real  = $this->fixture('admin-real.webp', 'ADMIN-REAL-BYTES');
        $extra = 'https://cdn.example.com/legit-extra.webp';

        $product = $this->createProduct([
            'slug'   => 'legit-extras-product',
            'image'  => $real,
            'images' => [$extra],
        ]);

        $thumbs = $this->thumbSources($this->detailHtml($product));

        // Real additional images are always preserved.
        $this->assertSame([asset($real), $extra], $thumbs);
    }

    public function test_detail_get_request_does_not_modify_product_data(): void
    {
        $real = $this->fixture('immutable.webp', 'IMMUTABLE-BYTES');
        $product = $this->createProduct([
            'slug'   => 'read-only-product',
            'image'  => $real,
            'images' => [$real, 'https://images.unsplash.com/photo-1516574187841-cb9cc2ca948b?auto=format&fit=crop&w=800&q=80'],
        ]);

        $before = [$product->fresh()->image, $product->fresh()->images];

        $this->detailHtml($product);
        $this->detailHtml($product);

        $after = [$product->fresh()->image, $product->fresh()->images];

        // GET must be read-only: rendering never writes image/gallery data.
        $this->assertSame($before, $after);
    }

    /* ------------------------------------------------------- create / edit */

    public function test_product_creation_does_not_store_duplicate_references(): void
    {
        $payload = [
            'title' => 'Dup Safe Product', 'subtitle' => 'Sub', 'description' => 'Desc',
            'price' => 99, 'special_price' => '', 'quantity' => 3,
            'stock_status' => 'in-stock', 'category' => 'Gadgets', 'status' => 1,
            'details' => "Detail one\nDetail two",
            'tags' => '',
            'image' => 'https://example.com/image1.webp',
            // Admin pastes the MAIN image URL into additional images as well.
            'additional_images' => "https://example.com/image1.webp\nhttps://example.com/image2.webp",
            // Two uploads of the SAME picture under different file names.
            'image_files' => [
                UploadedFile::fake()->createWithContent('photo-copy-a.png', self::PNG_1PX),
                UploadedFile::fake()->createWithContent('photo-copy-b.png', self::PNG_1PX),
            ],
        ];

        $this->actingAs($this->seller())
            ->withSession(['role' => 'seller'])
            ->post('/products', $payload)
            ->assertRedirect(route('products'))
            ->assertSessionHas('success');

        $row = Product::where('slug', 'dup-safe-product')->first();
        $this->assertNotNull($row);

        // Main image stays the main image and is NOT repeated in the gallery.
        $this->assertSame('https://example.com/image1.webp', $row->image);

        $images = $row->images;
        $this->assertCount(2, $images);
        $this->assertSame('https://example.com/image2.webp', $images[0]);

        // The byte-identical upload pair collapsed to a single stored entry.
        $this->assertStringContainsString('/uploads/products/', $images[1]);
        $pngEntries = array_filter($images, fn ($ref) => str_ends_with((string) $ref, '.png'));
        $this->assertCount(1, $pngEntries);

        // And the stored gallery is duplicate-free by identity.
        $this->assertSame($images, ProductImageService::uniqueList($images));

        // The store route moves uploads into the real public dir - remove
        // the fixtures this test created so the tree stays clean.
        foreach (glob(public_path('uploads/products/*-photo-copy-*.png')) ?: [] as $file) {
            @unlink($file);
        }
    }

    public function test_edit_form_prefills_and_saves_without_duplicates(): void
    {
        $seller = $this->seller();

        // Legacy row: main photo duplicated inside the gallery + a byte-copy.
        $main  = $this->fixture('edit-main.webp', 'MAIN-IMAGE-CONTENT');
        $copyA = $this->fixture('edit-copy-a.webp', 'MAIN-IMAGE-CONTENT');   // byte-copy of main
        $real  = $this->fixture('edit-real-extra.webp', 'REAL-EXTRA-CONTENT');

        // The product is owned by the editing seller (ownership controls
        // management access).
        $product = $this->createProduct([
            'slug'   => 'legacy-edit-product',
            'image'  => $main,
            'images' => [$main, $copyA, $real],
            'seller_id' => $seller->id,
        ]);

        // The edit form must NOT prefill the main image (or its duplicates).
        $editHtml = $this->actingAs($seller)
            ->get(route('products.edit', ['product' => $product->slug]))
            ->assertOk()
            ->getContent();

        $textarea = '';
        if (preg_match('/<textarea id="additional_images"[^>]*>(.*?)<\/textarea>/s', $editHtml, $m)) {
            $textarea = $m[1];
        }

        $this->assertStringNotContainsString($main, $textarea);
        $this->assertStringNotContainsString($copyA, $textarea);
        $this->assertStringContainsString($real, $textarea);

        // Saving with the prefilled extra plus one new URL cleans the row:
        // main stays main; gallery keeps only genuinely additional images.
        $payload = [
            'title' => $product->title, 'subtitle' => $product->subtitle,
            'description' => $product->description,
            'price' => 199.99, 'special_price' => '', 'quantity' => 5,
            'stock_status' => 'in-stock', 'category' => 'Gadgets', 'status' => 1,
            'details' => "Detail", 'tags' => '',
            // Admin also re-pastes the MAIN url - it must not come back twice.
            'additional_images' => $real . "\n" . $main . "\nhttps://example.com/new-shot.webp",
        ];

        $this->actingAs($seller)
            ->withSession(['role' => 'seller'])
            ->post(route('products.update', ['product' => $product->slug]), $payload)
            ->assertRedirect(route('products'))
            ->assertSessionHas('success');

        $row = $product->fresh();
        $this->assertSame($main, $row->image);
        $this->assertSame(
            [$real, 'https://example.com/new-shot.webp'],
            array_values((array) $row->images)
        );
    }

    public function test_editing_with_no_additional_images_empties_the_gallery(): void
    {
        $seller = $this->seller();

        // Legacy row: real main + real extra + phantom seed placeholder.
        $main        = $this->fixture('clear-main.webp', 'CLEAR-MAIN-BYTES');
        $extra       = $this->fixture('clear-extra.webp', 'CLEAR-EXTRA-BYTES');
        $placeholder = config('catalog.seed_products.signature-headphones.image');

        // Owned by the editing seller (ownership controls management access).
        $product = $this->createProduct([
            'slug'   => 'clear-gallery-product',
            'image'  => $main,
            'images' => [$main, $extra, $placeholder],
            'seller_id' => $seller->id,
        ]);

        // The prefilled textarea shows ONLY genuinely stored additional
        // images - never the main photo, byte-copies or the placeholder.
        $editHtml = $this->actingAs($seller)
            ->get(route('products.edit', ['product' => $product->slug]))
            ->assertOk()
            ->getContent();

        if (preg_match('/<textarea id="additional_images"[^>]*>(.*?)<\/textarea>/s', $editHtml, $m)) {
            $this->assertSame($extra, trim($m[1])); // prefill shows ONLY the real extra
        } else {
            $this->fail('Additional images textarea not found on the edit form.');
        }

        // Admin submits with NO additional images at all -> gallery empties.
        $this->actingAs($seller)
            ->withSession(['role' => 'seller'])
            ->post(route('products.update', ['product' => $product->slug]), [
                'title' => $product->title, 'subtitle' => $product->subtitle,
                'description' => $product->description,
                'price' => 199.99, 'special_price' => '', 'quantity' => 5,
                'stock_status' => 'in-stock', 'category' => 'Gadgets', 'status' => 1,
                'details' => 'Detail', 'tags' => '',
                // no additional_images key at all
            ])
            ->assertRedirect(route('products'))
            ->assertSessionHas('success');

        $row = $product->fresh();

        // Main stays main; NO phantom or stale additional image survives.
        $this->assertSame($main, $row->image);
        $this->assertSame([], array_values((array) $row->images));

        @unlink(public_path('uploads/products/clear-main.webp'));
        @unlink(public_path('uploads/products/clear-extra.webp'));
    }

    public function test_creation_with_only_uploaded_additional_photos_stores_exactly_them(): void
    {
        // Requirement scenario C: main upload + 2 additional uploads,
        // no URLs -> exactly 3 unique gallery images.
        $payload = [
            'title' => 'Uploads Only Product', 'subtitle' => 'Sub', 'description' => 'Desc',
            'price' => 49, 'special_price' => '', 'quantity' => 4,
            'stock_status' => 'in-stock', 'category' => 'Gadgets', 'status' => 1,
            'details' => 'Detail', 'tags' => '',
            'image_files' => [
                UploadedFile::fake()->createWithContent('shot-one.png', self::PNG_1PX),
                // Distinct content so the two uploads are genuinely different photos.
                UploadedFile::fake()->createWithContent('shot-two.png', self::PNG_1PX . '-alt-shot'),
            ],
        ];

        $this->actingAs($this->seller())
            ->withSession(['role' => 'seller'])
            ->post('/products', $payload)
            ->assertRedirect(route('products'))
            ->assertSessionHas('success');

        $row = Product::where('slug', 'uploads-only-product')->first();
        $this->assertNotNull($row);

        // Main photo became the uploaded default placeholder main; the two
        // distinct additional uploads are stored once each - nothing else.
        $images = array_values((array) $row->images);
        $this->assertCount(2, $images);
        $this->assertCount(2, array_unique($images));
        foreach ($images as $ref) {
            $this->assertStringContainsString('/uploads/products/', (string) $ref);
        }

        foreach (glob(public_path('uploads/products/*-shot-*.png')) ?: [] as $file) {
            @unlink($file);
        }
    }

    public function test_thumbnails_correspond_to_unique_images_and_still_switch(): void
    {
        $product = $this->createProduct([
            'slug'   => 'clicky-product',
            'image'  => '/uploads/products/one.webp',
            'images' => ['/uploads/products/two.webp', '/uploads/products/three.webp'],
        ]);

        $html   = $this->detailHtml($product);
        $thumbs = $this->thumbSources($html);

        // Every thumbnail is unique…
        $this->assertCount(3, $thumbs);
        $this->assertCount(3, array_unique($thumbs));

        // …and each one still drives the main-image switcher.
        $this->assertSame(
            3,
            substr_count($html, "document.getElementById('mainImage').src=this.src")
        );
        $this->assertSame(1, substr_count($html, 'id="mainImage"'));
    }

    public function test_variant_products_do_not_duplicate_the_gallery(): void
    {
        $payload = [
            'title' => 'Variant Gallery Product', 'subtitle' => 'Sub',
            'description' => 'Desc', 'price' => 499, 'special_price' => '',
            'quantity' => 10, 'stock_status' => 'in-stock', 'category' => 'Gadgets',
            'status' => 1, 'details' => "Detail", 'tags' => '',
            'image' => 'https://example.com/v-main.webp',
            'additional_images' => "https://example.com/v-alt.webp",
            'options' => [
                'name'   => ['Color'],
                'values' => ['Black, White'],
            ],
            'variants' => [
                'data'  => ['{"Color":"Black"}', '{"Color":"White"}'],
                'price' => ['499', '509'],
                'stock' => ['10', '8'],
                'sku'   => ['', ''],
            ],
        ];

        $this->actingAs($this->seller())
            ->withSession(['role' => 'seller'])
            ->post('/products', $payload)
            ->assertRedirect(route('products'));

        $row = Product::where('slug', 'variant-gallery-product')->first();
        $this->assertNotNull($row);
        $this->assertNotEmpty($row->variants);

        $html   = $this->detailHtml($row);
        $thumbs = $this->thumbSources($html);

        // Exactly one hero image and a unique two-entry gallery (main + alt),
        // regardless of the attached options/variants.
        $this->assertSame(1, substr_count($html, 'id="mainImage"'));
        $this->assertSame([
            asset('https://example.com/v-main.webp'),
            asset('https://example.com/v-alt.webp'),
        ], $thumbs);

        // Variant data is embedded for the JS selector and the gallery stays clean.
        $this->assertStringContainsString('const productVariants =', $html);
        $this->assertSame($thumbs, array_values(array_unique($thumbs)));
    }
}



