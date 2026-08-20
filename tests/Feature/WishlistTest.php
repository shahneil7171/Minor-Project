<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WishlistItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WishlistTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::disk('local')->delete('custom_products_test.json');
    }

    private function buyer(): User
    {
        return User::factory()->create(['account_type' => 'buyer']);
    }

    private function postToggle(User $user, string $slug)
    {
        return $this->actingAs($user)
            ->withHeaders(['Referer' => '/products'])
            ->post('/wishlist/toggle/' . $slug);
    }

    public function test_guest_is_redirected_from_wishlist()
    {
        $response = $this->get('/wishlist');

        $response->assertRedirect('/login');
        $response->assertStatus(302);
    }

    public function test_buyer_can_add_a_product_to_wishlist()
    {
        $buyer = $this->buyer();

        $response = $this->postToggle($buyer, 'smart-watch-pro');

        $response->assertRedirect('/products');
        $response->assertSessionHas('status', 'Added to wishlist.');

        $this->assertTrue(
            WishlistItem::where('user_id', $buyer->id)
                ->where('product_slug', 'smart-watch-pro')
                ->exists()
        );
    }

    public function test_toggling_twice_removes_the_product(): void
    {
        $buyer = $this->buyer();

        $this->postToggle($buyer, 'smart-watch-pro');
        $this->postToggle($buyer, 'smart-watch-pro');

        $this->assertSame(0, WishlistItem::where('user_id', $buyer->id)->count());
    }

    public function test_wishlist_page_lists_saved_products(): void
    {
        $buyer = $this->buyer();
        WishlistItem::create([
            'user_id' => $buyer->id,
            'product_slug' => 'smart-watch-pro',
        ]);

        $response = $this->actingAs($buyer)->get('/wishlist');

        $response->assertOk();
        $response->assertViewIs('wishlist');
    }

    public function test_remove_deletes_the_wishlist_item(): void
    {
        $buyer = $this->buyer();
        WishlistItem::create([
            'user_id' => $buyer->id,
            'product_slug' => 'smart-watch-pro',
        ]);

        $response = $this->actingAs($buyer)
            ->withHeaders(['Referer' => '/wishlist'])
            ->post('/wishlist/remove/smart-watch-pro');

        $response->assertRedirect('/wishlist');
        $this->assertSame(0, WishlistItem::where('user_id', $buyer->id)->count());
    }

    public function test_move_to_cart_transfers_the_item_and_clears_wishlist(): void
    {
        $buyer = $this->buyer();

        $response = $this->actingAs($buyer)
            ->withSession(['cart' => []])
            ->post('/wishlist/to-cart/smart-watch-pro');

        $response->assertRedirect('/cart');

        $cart = session('cart');
        $this->assertArrayHasKey('smart-watch-pro', $cart);
        $this->assertSame(1, $cart['smart-watch-pro']['quantity']);
        $this->assertSame(0, WishlistItem::where('user_id', $buyer->id)->count());
    }

    public function test_seller_cannot_move_items_to_cart(): void
    {
        $seller = User::factory()->create(['account_type' => 'seller']);
        WishlistItem::create([
            'user_id' => $seller->id,
            'product_slug' => 'smart-watch-pro',
        ]);

        $response = $this->actingAs($seller)
            ->withHeaders(['Referer' => '/wishlist'])
            ->post('/wishlist/to-cart/smart-watch-pro');

        $response->assertSessionHas('error', 'Sellers cannot add items to cart.');

        // The item stays in the wishlist for later.
        $this->assertSame(1, WishlistItem::where('user_id', $seller->id)->count());
    }
}