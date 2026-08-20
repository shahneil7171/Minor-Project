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

        $cart = session('cart');
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

        $products = json_decode(Storage::disk('local')->get('custom_products_test.json'), true);
        $this->assertArrayHasKey('wireless-t-shirt', $products);

        $prod = $products['wireless-t-shirt'];
        $this->assertCount(2, $prod['options']);
        $this->assertCount(6, $prod['variants']);

        $mBlack = collect($prod['variants'])->firstWhere(
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
        $this->assertEmpty(session('cart'));
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

        $cart = session('cart');
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
     * Write a variant product directly into the testing JSON store.
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

        $product = [
            'title' => 'Wireless T-Shirt',
            'subtitle' => 'Cool',
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
            'slug' => 'wireless-t-shirt',
            'tags' => [],
            'options' => $options,
            'variants' => $variants,
        ];

        Storage::disk('local')->put(
            'custom_products_test.json',
            json_encode(['wireless-t-shirt' => $product])
        );

        return $product;
    }
}