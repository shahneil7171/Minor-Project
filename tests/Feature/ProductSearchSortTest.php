<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
