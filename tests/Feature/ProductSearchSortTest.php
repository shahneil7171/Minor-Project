<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductSearchSortTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Isolate JSON-backed product storage used by the routes under test.
        Storage::disk('local')->delete('custom_products_test.json');
    }

    public function test_search_products_by_title()
    {
        $user = User::factory()->create(['account_type' => 'buyer']);
        
        $response = $this->actingAs($user)->get('/products?search=watch');
        
        $response->assertOk();
        $response->assertSee('Smart Watch Pro');
        $response->assertViewHas('search', 'watch');
    }

    public function test_search_no_results()
    {
        $user = User::factory()->create(['account_type' => 'buyer']);
        
        $response = $this->actingAs($user)->get('/products?search=nonexistent');
        
        $response->assertOk();
        $response->assertSee('No products found matching your search');
    }

    public function test_sort_price_low_to_high()
    {
        $user = User::factory()->create(['account_type' => 'buyer']);
        
        $response = $this->actingAs($user)->get('/products?sort=price-asc');
        
        $response->assertOk();
        $response->assertViewHas('sort', 'price-asc');
    }

    public function test_sort_price_high_to_low()
    {
        $user = User::factory()->create(['account_type' => 'buyer']);
        
        $response = $this->actingAs($user)->get('/products?sort=price-desc');
        
        $response->assertOk();
        $response->assertViewHas('sort', 'price-desc');
    }

    public function test_seller_can_edit_default_product()
    {
        $seller = User::factory()->create(['account_type' => 'seller']);
        
        $response = $this->actingAs($seller)->get('/products/smart-watch-pro/edit');
        
        $response->assertOk();
        $response->assertSee('Smart Watch Pro');
    }

    public function test_seller_cannot_edit_if_not_seller()
    {
        $buyer = User::factory()->create(['account_type' => 'buyer']);
        
        $response = $this->actingAs($buyer)->get('/products/smart-watch-pro/edit');
        
        $response->assertRedirect('/products');
        $response->assertSessionHas('error', 'Only sellers or admins can edit products.');
    }

    public function test_seller_can_update_default_product()
    {
        $seller = User::factory()->create(['account_type' => 'seller']);
        
        $response = $this->actingAs($seller)->post('/products/smart-watch-pro/update', [
            'title'        => 'Updated Smart Watch',
            'subtitle'     => 'Updated subtitle',
            'description'  => 'Updated description',
            'price'        => 299,
            'special_price'=> 249,
            'quantity'     => 10,
            'stock_status' => 'in-stock',
            'category'     => 'Electronics',
            'subcategory'  => 'Accessories',
            'brand'        => 'Test Brand',
            'tax'          => 18,
            'status'       => 1,
            'tags'         => 'watch, smart',
            'details'      => 'Feature 1\nFeature 2',
        ]);
        
        $response->assertRedirect('/products');
        $response->assertSessionHas('success', 'Product updated successfully.');
    }

    /**
     * Build a complete product record that can live in the JSON product
     * store used by the routes (custom_products_test.json while testing).
     */
    private function product(string $title, array $overrides = []): array
    {
        return array_merge([
            'title'         => $title,
            'subtitle'      => "Subtitle for {$title}",
            'description'   => "Description for {$title}",
            'image'         => 'https://images.unsplash.com/photo-1518444209757-9ae0b9eb3734?auto=format&fit=crop&w=800&q=80',
            'images'        => [],
            'details'       => ['Feature'],
            'price'         => 99,
            'special_price' => null,
            'quantity'      => 10,
            'stock_status'  => 'in-stock',
            'category'      => 'Electronics',
            'subcategory'   => null,
            'brand'         => 'Samsung',
            'tax'           => 18,
            'status'        => 1,
            'tags'          => [],
            'options'       => [],
            'variants'      => [],
        ], $overrides);
    }

    /**
     * Spec cases 2/3: "sams", "SAMS", "Samsung" and partial terms all
     * match the same Samsung product through title, brand, SKU and tags.
     */
    public function test_search_is_case_insensitive_and_partial()
    {
        Storage::disk('local')->put('custom_products_test.json', json_encode([
            'samsung-galaxy-s26' => $this->product('Samsung Galaxy S26 Ultra', [
                'sku'   => 'SAM-GAL-26',
                'tags'  => ['phone', 'mobile', 'galaxy'],
                'price' => 1300,
            ]),
        ]));

        $user = User::factory()->create(['account_type' => 'buyer']);

        foreach (['sams', 'SAMS', 'Samsung', 'galaxy', 'SAM-GAL'] as $query) {
            $response = $this->actingAs($user)->get('/products?search='.urlencode($query));

            $response->assertOk();
            $response->assertSee('Samsung Galaxy S26 Ultra');
            $response->assertViewHas('search', $query);
        }
    }

    /**
     * Spec field coverage: search matches brand, SKU and tags.
     */
    public function test_search_matches_brand_sku_and_tags()
    {
        Storage::disk('local')->put('custom_products_test.json', json_encode([
            'samsung-charger' => $this->product('Super Fast Charger', [
                'brand' => 'Samsung',
                'sku'   => 'SAM-CHG-25',
                'tags'  => ['charger', 'accessories'],
            ]),
        ]));

        $user = User::factory()->create(['account_type' => 'buyer']);

        $this->actingAs($user)->get('/products?search=samsung')->assertSee('Super Fast Charger');
        $this->actingAs($user)->get('/products?search=SAM-CHG-25')->assertSee('Super Fast Charger');
        $this->actingAs($user)->get('/products?search=SAM-CHG')->assertSee('Super Fast Charger');
        $this->actingAs($user)->get('/products?search=accessories')->assertSee('Super Fast Charger');
    }

    /**
     * Multi-word queries use AND semantics: every term must match.
     */
    public function test_search_requires_all_terms_when_multiple_words_used()
    {
        Storage::disk('local')->put('custom_products_test.json', json_encode([
            'samsung-phone' => $this->product('Samsung Phone 25', ['tags' => ['phone']]),
            'samsung-watch' => $this->product('Samsung Smartwatch Pro', ['tags' => ['smartwatch']]),
        ]));

        $user = User::factory()->create(['account_type' => 'buyer']);

        $response = $this->actingAs($user)->get('/products?search='.urlencode('samsung smart'));
        $response->assertOk();
        $response->assertSee('Samsung Smartwatch Pro');
        $response->assertDontSee('Samsung Phone 25');
    }

    /**
     * Spec case 6: search combines with the existing category filter.
     */
    public function test_search_and_category_filter_work_together()
    {
        Storage::disk('local')->put('custom_products_test.json', json_encode([
            'samsung-phone'   => $this->product('Samsung Phone 25', ['category' => 'Electronics']),
            'samsung-sneaker' => $this->product('Samsung Sneakers', ['category' => 'Fashion']),
        ]));

        $user = User::factory()->create(['account_type' => 'buyer']);

        $all = $this->actingAs($user)->get('/products?search=Samsung');
        $all->assertSee('Samsung Phone 25');
        $all->assertSee('Samsung Sneakers');

        $filtered = $this->actingAs($user)->get('/products?search=Samsung&category=Electronics');
        $filtered->assertOk();
        $filtered->assertViewHas('category', 'Electronics');
        $filtered->assertSee('Samsung Phone 25');
        $filtered->assertDontSee('Samsung Sneakers');
    }

    /**
     * Search + sort + category all apply at the same time.
     */
    public function test_search_sort_and_category_combine()
    {
        Storage::disk('local')->put('custom_products_test.json', json_encode([
            'samsung-phone'  => $this->product('Samsung Phone 25', ['category' => 'Electronics', 'price' => 800]),
            'samsung-watch'  => $this->product('Samsung Watch 8', ['category' => 'Electronics', 'price' => 350]),
            'samsung-tshirt' => $this->product('Samsung T-Shirt', ['category' => 'Fashion', 'price' => 20]),
        ]));

        $user = User::factory()->create(['account_type' => 'buyer']);

        $response = $this->actingAs($user)->get('/products?search=Samsung&category=Electronics&sort=price-asc');
        $response->assertOk();
        $response->assertViewHas('search', 'Samsung');
        $response->assertViewHas('category', 'Electronics');
        $response->assertViewHas('sort', 'price-asc');
        $response->assertSee('Samsung Watch 8');
        $response->assertSee('Samsung Phone 25');
        $response->assertDontSee('Samsung T-Shirt');

        $paginator = $response->viewData('products');
        $items = array_values($paginator->items());
        $this->assertSame('Samsung Watch 8', $items[0]['title']);
        $this->assertSame('Samsung Phone 25', $items[1]['title']);
    }

    /**
     * Spec case 4: no-results message shows the term + a Clear Search action.
     */
    public function test_no_results_message_includes_term_and_clear_search()
    {
        $user = User::factory()->create(['account_type' => 'buyer']);

        $response = $this->actingAs($user)->get('/products?search=nonexistentproduct123');

        $response->assertOk();
        $response->assertSee('No products found matching your search for "nonexistentproduct123"', false);
        $response->assertSee('Clear Search');
        $response->assertViewHas('search', 'nonexistentproduct123');
    }

    /**
     * Spec case 5: an empty search simply shows the normal product listing.
     */
    public function test_empty_search_shows_normal_product_listing()
    {
        $user = User::factory()->create(['account_type' => 'buyer']);

        $response = $this->actingAs($user)->get('/products');
        $response->assertOk();
        foreach (['Smart Watch Pro', 'Signature Headphones', 'Premium Backpack'] as $title) {
            $response->assertSee($title);
        }

        $withEmpty = $this->actingAs($user)->get('/products?search=');
        $withEmpty->assertOk();
        foreach (['Smart Watch Pro', 'Signature Headphones', 'Premium Backpack'] as $title) {
            $withEmpty->assertSee($title);
        }
    }

    /**
     * Spec case 7: pagination keeps the search query on page 2.
     */
    public function test_pagination_preserves_search_query_on_page_two()
    {
        $products = [];
        for ($i = 1; $i <= 7; $i++) {
            $products["samsung-phone-{$i}"] = $this->product("Samsung Phone Test {$i}");
        }
        Storage::disk('local')->put('custom_products_test.json', json_encode($products));

        $user = User::factory()->create(['account_type' => 'buyer']);

        $page1 = $this->actingAs($user)->get('/products?search=Samsung&page=1');
        $page1->assertOk();
        $page1->assertSee('Samsung Phone Test 1');
        $page1->assertSee('Samsung Phone Test 6');
        $page1->assertDontSee('Samsung Phone Test 7');

        $page2 = $this->actingAs($user)->get('/products?search=Samsung&page=2');
        $page2->assertOk();
        $page2->assertSee('Samsung Phone Test 7');

        // Search text stays in the box and stays in the pagination links.
        $page2->assertSee('value="Samsung"', false);
        $page2->assertSee('search=Samsung', false);
        $page2->assertViewHas('search', 'Samsung');

        $paginator = $page2->viewData('products');
        $this->assertInstanceOf(LengthAwarePaginator::class, $paginator);
        $this->assertSame(2, $paginator->currentPage());
        $this->assertSame(7, $paginator->total());
    }

    /**
     * Spec cases 8/9: a found product opens its detail page and can be
     * added to the cart exactly as before.
     */
    public function test_search_result_opens_detail_and_adds_to_cart()
    {
        $user = User::factory()->create(['account_type' => 'buyer']);

        $results = $this->actingAs($user)->get('/products?search=watch');
        $results->assertOk();
        $results->assertSee('Smart Watch Pro');
        $results->assertSee(route('product.show', ['product' => 'smart-watch-pro']));

        $detail = $this->actingAs($user)->get(route('product.show', ['product' => 'smart-watch-pro']));
        $detail->assertOk();
        $detail->assertSee('Smart Watch Pro');

        $add = $this->actingAs($user)->post(route('cart.add', ['product' => 'smart-watch-pro']));
        $add->assertRedirect(route('cart.index'));

        $cart = $this->actingAs($user)->get(route('cart.index'));
        $cart->assertOk();
        $cart->assertSee('Smart Watch Pro');
    }

    /**
     * Spec case 10: seller product management still works on the Products
     * page (Add / Edit actions plus searching their own catalog).
     */
    public function test_seller_products_page_search_and_management_still_work()
    {
        $seller = User::factory()->create(['account_type' => 'seller']);

        $response = $this->actingAs($seller)->get('/products');
        $response->assertOk();
        $response->assertSee('Add product');
        $response->assertSee('Smart Watch Pro');
        $response->assertSee('Edit');

        $search = $this->actingAs($seller)->get('/products?search=headphones');
        $search->assertOk();
        $search->assertSee('Signature Headphones');
        $search->assertSee('Edit');
    }

    /**
     * Hostile / overlong search strings are handled safely and gracefully.
     */
    public function test_search_handles_special_characters_and_very_long_input()
    {
        $user = User::factory()->create(['account_type' => 'buyer']);

        $hostile = $this->actingAs($user)->get('/products?search='.urlencode("' OR 1=1 --\"\"%"));
        $hostile->assertOk();
        $hostile->assertSee('No products found matching your search');

        $long = $this->actingAs($user)->get('/products?search='.str_repeat('a', 500));
        $long->assertOk();
        $long->assertSee('No products found matching your search');
    }
}
