<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductVariantTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::disk('local')->delete('custom_products_test.json');
    }

    private function seller(): User
    {
        return User::factory()->create(['account_type' => 'seller']);
    }

    private function buyer(): User
    {
        return User::factory()->create(['account_type' => 'buyer']);
    }

    public function test_plain_product_can_still_be_purchased_without_options(): void
    {
        $response = $this->actingAs($this->buyer())
            ->withSession(['role' => 'buyer'])
            ->post('/cart/add/smart-watch-pro', ['quantity' => 2]);

        $response->assertRedirect('/cart');

        $cart = app(\App\Services\CartService::class)->lines();
        $this->assertArrayHasKey('smart-watch-pro', $cart);
        $this->assertEquals(2, $cart['smart-watch-pro']['quantity']);
    }

    public function test_seller_can_add_a_product_with_options_and_variant_pricing(): void
    {
        $response = $this->actingAs($this->seller())
            ->withSession(['role' => 'seller'])
            ->post('/products', [
                'title' => 'Wireless T-Shirt',
                'subtitle' => 'Cool t-shirt',
                'description' => 'Premium cotton',
                'price' => 499,
                'special_price' => 0,
                'quantity' => 10,
                'stock_status' => 'in-stock',
                'category' => 'Electronics',
                'subcategory' => 'Accessories',
                'brand' => 'KDP',
                'tax' => 0,
                'status' => 1,
                'details' => "Breathable\nSoft",
                'options' => [
                    'name' => ['Size', 'Color'],
                    'values' => ['S, M, L', 'Black, White'],
                ],
                'variants' => [
                    'data' => [
                        '{"Size":"S","Color":"Black"}',
                        '{"Size":"S","Color":"White"}',
                        '{"Size":"M","Color":"Black"}',
                        '{"Size":"M","Color":"White"}',
                        '{"Size":"L","Color":"Black"}',
                        '{"Size":"L","Color":"White"}',
                    ],
                    'price' => ['499', '509', '549', '559', '599', '609'],
                    'stock' => ['10', '8', '5', '4', '2', '3'],
                    'sku' => ['TS-S-BK', 'TS-S-WH', 'TS-M-BK', 'TS-M-WH', 'TS-L-BK', 'TS-L-WH'],
                ],
            ]);

        $response->assertRedirect('/products');
        $response->assertSessionHas('success');

        $prod = \App\Models\Product::where('slug', 'wireless-t-shirt')->first();
        $this->assertNotNull($prod);
        $this->assertCount(2, $prod->options);
        $this->assertCount(6, $prod->variants);

        $variants = $prod->variants;
        $mBlack = collect($variants)->firstWhere(
            fn ($v) => ($v['values']['Size'] ?? null) === 'M' && ($v['values']['Color'] ?? null) === 'Black'
        );
        $this->assertNotNull($mBlack);
        $this->assertEquals(549, $mBlack['price']);
        $this->assertEquals(5, $mBlack['stock']);
        $this->assertEquals('TS-M-BK', $mBlack['sku']);
    }

    public function test_adding_a_variant_without_selecting_options_is_rejected(): void
    {
        $this->seedVariantProduct();

        $response = $this->actingAs($this->buyer())
            ->withSession(['role' => 'buyer'])
            ->post('/cart/add/wireless-t-shirt', ['quantity' => 1]);

        $response->assertRedirect('/products/wireless-t-shirt');
        $response->assertSessionHas(
            'error',
            'Please select all the required options before adding this product to your cart.'
        );
        $this->assertEmpty(app(\App\Services\CartService::class)->lines());
    }
public function test_different_variants_are_separate_cart_lines(): void
    {
        $product = $this->seedVariantProduct();
        $mBlack = collect($product['variants'])->firstWhere(
            fn ($v) => ($v['values']['Size'] ?? null) === 'M' && ($v['values']['Color'] ?? null) === 'Black'
        );
        $lBlack = collect($product['variants'])->firstWhere(
            fn ($v) => ($v['values']['Size'] ?? null) === 'L' && ($v['values']['Color'] ?? null) === 'Black'
        );

        $buyer = $this->buyer();
        $this->actingAs($buyer)->withSession(['role' => 'buyer'])
            ->post('/cart/add/wireless-t-shirt', ['quantity' => 2, 'variant_id' => $mBlack['id']]);
        $this->actingAs($buyer)->withSession(['role' => 'buyer'])
            ->post('/cart/add/wireless-t-shirt', ['quantity' => 1, 'variant_id' => $lBlack['id']]);

        $cart = app(\App\Services\CartService::class)->lines();
        $this->assertCount(2, $cart);

        $mKey = 'wireless-t-shirt::' . $mBlack['id'];
        $lKey = 'wireless-t-shirt::' . $lBlack['id'];

        $this->assertArrayHasKey($mKey, $cart);
        $this->assertArrayHasKey($lKey, $cart);
        $this->assertEquals(2, $cart[$mKey]['quantity']);
        $this->assertEquals(549, $cart[$mKey]['price']);
        $this->assertEquals('Size: M | Color: Black', $cart[$mKey]['options_text']);
        $this->assertEquals(1, $cart[$lKey]['quantity']);
    }

    public function test_variant_options_survive_through_checkout(): void
    {
        $product = $this->seedVariantProduct();
        $mWhite = collect($product['variants'])->firstWhere(
            fn ($v) => ($v['values']['Size'] ?? null) === 'M' && ($v['values']['Color'] ?? null) === 'White'
        );

        $buyer = $this->buyer();
        $this->actingAs($buyer)->withSession(['role' => 'buyer'])
            ->post('/cart/add/wireless-t-shirt', ['quantity' => 2, 'variant_id' => $mWhite['id']]);

        $review = $this->actingAs($buyer)->get('/checkout/review');
        $review->assertOk();
        $this->assertStringContainsString('Size: M | Color: White', $review->getContent());
        $submit = $this->actingAs($buyer)->post('/checkout', [
            'address_option' => 'new',
            'shipping_method' => 'standard',
            'payment_method' => 'cod',
            'new_address' => [
                'full_name' => 'Alice',
                'phone' => '1234567890',
                'house_number' => '1',
                'street_address' => 'Main Street',
                'city' => 'Metropolis',
                'state' => 'State',
                'pincode' => '10001',
                'country' => 'India',
            ],
        ]);
        $submit->assertRedirect('/checkout/complete');

        $complete = $this->actingAs($buyer)->get('/checkout/complete');
        $complete->assertOk();
        $this->assertStringContainsString('Size: M | Color: White', $complete->getContent());
    }

    public function test_product_detail_page_renders_options_dynamically(): void
    {
        $this->seedVariantProduct();

        $response = $this->actingAs($this->buyer())->get('/products/wireless-t-shirt');
        $response->assertOk();
        $response->assertSee('Select Options');
        $response->assertSee('Size');
        $response->assertSee('Color');
        $response->assertSee('variantIdInput');
        $response->assertSee('variantStatus');
    }

    /**
     * Regression guard: the option/variant editor is inline JavaScript. If a
     * <script> tag is ever left unclosed again, the browser swallows the rest
     * of the document as JavaScript, throws a SyntaxError and NONE of the
     * handlers get registered — which made "+ Add option" and "Generate
     * variants" silently do nothing.
     */
    public function test_admin_product_pages_close_their_script_tag_and_wire_the_variant_buttons(): void
    {
        $seller = $this->seller();
        $this->seedVariantProduct();

        // The editing account must OWN the product (ownership controls
        // management access, not just the seller role).
        \App\Models\Product::where('slug', 'wireless-t-shirt')
            ->firstOrFail()
            ->update(['seller_id' => $seller->id]);

        foreach (['/products/create', '/products/wireless-t-shirt/edit'] as $url) {
            $html = $this->actingAs($seller)
                ->withSession(['role' => 'seller'])
                ->get($url)
                ->assertOk()
                ->getContent();

            $this->assertSame(
                substr_count($html, '<script'),
                substr_count($html, '</script>'),
                "Every <script> opened on {$url} must be closed or the variant editor never executes."
            );
            $this->assertStringContainsString('function addOption(', $html);
            $this->assertStringContainsString('onclick="addOption()"', $html);
            $this->assertStringContainsString('function generateVariants(', $html);
            $this->assertStringContainsString('onclick="generateVariants()"', $html);
            $this->assertStringContainsString('<div id="optionsContainer"', $html);
            $this->assertStringContainsString('<div id="variantsContainer"', $html);
        }
    }

    public function test_blank_variant_skus_are_auto_generated_and_unique(): void
    {
        $this->actingAs($this->seller())
            ->withSession(['role' => 'seller'])
            ->post('/products', [
                'title' => 'Galaxy Variant Phone',
                'sku' => 'S26U',
                'subtitle' => 'Flagship',
                'description' => 'Every combination',
                'price' => 1199,
                'special_price' => 0,
                'quantity' => 30,
                'stock_status' => 'in-stock',
                'category' => 'Electronics',
                'status' => 1,
                'options' => [
                    'name' => ['Storage', 'Color'],
                    'values' => ['256GB, 512GB', 'Black, Gray'],
                ],
                'variants' => [
                    'data' => [
                        '{"Storage":"256GB","Color":"Black"}',
                        '{"Storage":"256GB","Color":"Gray"}',
                        '{"Storage":"512GB","Color":"Black"}',
                        '{"Storage":"512GB","Color":"Gray"}',
                    ],
                    'price' => ['1199', '1199', '1299', '1299'],
                    'stock' => ['10', '10', '8', '8'],
                    // Every SKU intentionally blank -> service must generate them.
                    'sku' => ['', '', '', ''],
                ],
            ])->assertRedirect('/products')->assertSessionHas('success');

        $prod = \App\Models\Product::where('slug', 'galaxy-variant-phone')->first();
        $this->assertNotNull($prod);

        $skus = array_column($prod->variants, 'sku');
        $this->assertCount(4, $skus);
        $this->assertCount(4, array_unique($skus), 'Generated SKUs must be unique.');
        $this->assertContains('S26U-256GB-BLACK', $skus);
        $this->assertContains('S26U-512GB-GRAY', $skus);

        // Prices/stock stay per-variant even when SKUs are generated.
        $bySku = array_combine($skus, $prod->variants);
        $this->assertSame(1299.0, (float) $bySku['S26U-512GB-BLACK']['price']);
        $this->assertSame(8, (int) $bySku['S26U-512GB-BLACK']['stock']);
    }

    public function test_duplicate_variant_skus_are_rejected_without_creating_the_product(): void
    {
        $this->actingAs($this->seller())
            ->withSession(['role' => 'seller'])
            ->from('/products/create')
            ->post('/products', [
                'title' => 'Duplicate SKU Phone',
                'description' => 'Two variants sharing one SKU',
                'price' => 100,
                'special_price' => 0,
                'quantity' => 5,
                'stock_status' => 'in-stock',
                'category' => 'Electronics',
                'status' => 1,
                'options' => [
                    'name' => ['Color'],
                    'values' => ['Black, White'],
                ],
                'variants' => [
                    'data' => [
                        '{"Color":"Black"}',
                        '{"Color":"White"}',
                    ],
                    'price' => ['100', '100'],
                    'stock' => ['5', '5'],
                    'sku' => ['DUP-SHARED', 'DUP-SHARED'],
                ],
            ])->assertRedirect('/products/create')
              ->assertSessionHasErrors('variants');

        $this->assertNull(\App\Models\Product::where('slug', 'duplicate-sku-phone')->first());
    }

    /**
     * Insert a variant product directly into the products table.
     */
    private function seedVariantProduct(): array
    {
        $options = [
            ['name' => 'Size', 'values' => ['S', 'M', 'L']],
            ['name' => 'Color', 'values' => ['Black', 'White']],
        ];

        $priceMap = [0 => 499, 1 => 509, 2 => 549, 3 => 559, 4 => 599, 5 => 609];
        $stockMap = [0 => 10, 1 => 8, 2 => 5, 3 => 4, 4 => 2, 5 => 3];

        $variants = [];
        $idx = 0;
        foreach ($options[0]['values'] as $size) {
            foreach ($options[1]['values'] as $color) {
                $values = ['Size' => $size, 'Color' => $color];
                ksort($values, SORT_STRING);
                $variants[] = [
                    'id'     => 'v' . substr(md5(json_encode($values)), 0, 12),
                    'values' => ['Size' => $size, 'Color' => $color],
                    'sku'    => 'TS-' . $size . '-' . substr($color, 0, 2),
                    'price'  => $priceMap[$idx],
                    'stock'  => $stockMap[$idx],
                ];
                $idx++;
            }
        }

        \App\Models\Product::create([
            'slug'          => 'wireless-t-shirt',
            'title'         => 'Wireless T-Shirt',
            'subtitle'      => 'Cool',
            'description'   => 'Premium cotton',
            'price'         => 499,
            'special_price' => null,
            'quantity'      => 10,
            'stock_status'  => 'in-stock',
            'category_id'   => app(\App\Services\ProductCatalogService::class)->resolveCategory('Electronics')?->id,
            'category'      => 'Electronics',
            'subcategory'   => 'Accessories',
            'brand'         => 'KDP',
            'tax'           => 0,
            'status'        => 1,
            'tags'          => [],
            'options'       => $options,
            'variants'      => $variants,
            'is_seed'       => false,
        ]);

        return [
            'title' => 'Wireless T-Shirt',
            'options' => $options,
            'variants' => $variants,
        ];
    }
}
