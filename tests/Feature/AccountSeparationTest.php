<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountSeparationTest extends TestCase
{
    use RefreshDatabase;

    public function test_buyer_cannot_access_seller_create_product_page()
    {
        $buyer = User::factory()->create(['account_type' => 'buyer']);
        
        $response = $this->actingAs($buyer)->get('/products/create');
        
        $response->assertRedirect('/products');
        $response->assertSessionHas('error', 'Only sellers or admins can add products.');
    }

    public function test_seller_cannot_access_cart()
    {
        $seller = User::factory()->create(['account_type' => 'seller']);
        
        $response = $this->actingAs($seller)->post('/cart/add/smart-watch-pro');
        
        $response->assertRedirect('/products');
        $response->assertSessionHas('error', 'Sellers cannot add items to cart.');
    }

    public function test_seller_cannot_purchase_items()
    {
        $seller = User::factory()->create(['account_type' => 'seller']);
        
        $response = $this->actingAs($seller)->post('/cart/buy-now/smart-watch-pro');
        
        $response->assertRedirect('/products');
        $response->assertSessionHas('error', 'Sellers cannot purchase items.');
    }

    public function test_buyer_account_type_persists_after_login()
    {
        $buyer = User::factory()->create(['email' => 'buyer@example.com', 'account_type' => 'buyer']);
        
        $response = $this->post('/login', [
            'email' => 'buyer@example.com',
            'password' => 'password',
        ]);
        
        $response->assertRedirect('/');
        $this->assertAuthenticated();
        
        $user = auth()->user();
        $this->assertEquals('buyer', $user->account_type);
    }

    public function test_seller_account_type_persists_after_login()
    {
        $seller = User::factory()->create(['email' => 'seller@example.com', 'account_type' => 'seller']);
        
        $response = $this->post('/login', [
            'email' => 'seller@example.com',
            'password' => 'password',
        ]);
        
        $response->assertRedirect('/');
        $this->assertAuthenticated();
        
        $user = auth()->user();
        $this->assertEquals('seller', $user->account_type);
    }

    public function test_registration_requires_account_type()
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);
        
        $response->assertSessionHasErrors('account_type');
    }

    public function test_register_as_buyer()
    {
        $response = $this->post('/register', [
            'name' => 'Test Buyer',
            'email' => 'buyer@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'account_type' => 'buyer',
        ]);
        
        $response->assertRedirect('/');
        
        $user = User::where('email', 'buyer@example.com')->first();
        $this->assertNotNull($user);
        $this->assertEquals('buyer', $user->account_type);
    }

    public function test_register_as_seller()
    {
        $response = $this->post('/register', [
            'name' => 'Test Seller',
            'email' => 'seller@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'account_type' => 'seller',
        ]);
        
        $response->assertRedirect('/');
        
        $user = User::where('email', 'seller@example.com')->first();
        $this->assertNotNull($user);
        $this->assertEquals('seller', $user->account_type);
    }

    public function test_seller_can_access_create_product_page()
    {
        $seller = User::factory()->create(['account_type' => 'seller']);
        
        $response = $this->actingAs($seller)->get('/products/create');
        
        $response->assertOk();
        $response->assertViewIs('add-product');
    }
}
