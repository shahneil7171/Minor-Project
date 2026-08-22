<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\User;
use App\Services\ProductCatalogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Verifies the persistent, per-user cart architecture:
 *
 * 1. A customer's cart is stored in the database (keyed by user_id) and
 *    survives logout.
 * 2. Another account (admin) never sees or inherits another user's cart.
 * 3. Signing back in restores the customer's exact cart lines.
 * 4. Guest carts merge into the user's persistent cart on login.
 * 5. Checkout clears only the purchasing customer's cart.
 * 6. Carts are isolated between two different customers.
 */
class CartPersistenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Isolate JSON-backed product storage used by these tests.
        Storage::disk('local')->delete('custom_products_test.json');
    }

    public function test_customer_cart_persists_across_logout_and_admin_login(): void
    {
        $customer = User::factory()->create(['account_type' => 'buyer', 'status' => 'active']);
        $admin = User::factory()->create(['account_type' => 'admin', 'status' => 'active']);

        // 1-2. Customer A adds two products.
        $this->actingAs($customer)
            ->post(route('cart.add', ['product' => 'signature-headphones']))
            ->assertRedirect(route('cart.index'));

        $this->actingAs($customer)
            ->post(route('cart.add', ['product' => 'smart-watch-pro']))
            ->assertRedirect(route('cart.index'));

        // 3. Both products are visible in the cart.
        $this->actingAs($customer)
            ->get(route('cart.index'))
            ->assertOk()
            ->assertSee('Signature Headphones')
            ->assertSee('Smart Watch Pro');

        // The lines live in the database, keyed by the customer.
        $this->assertDatabaseHas('carts', ['user_id' => $customer->id]);
        $this->assertSame(2, CartItem::whereHas('cart', fn ($q) => $q->where('user_id', $customer->id))->count());

        // 4. Logout — the database cart must NOT be deleted.
        $this->post(route('logout'))->assertRedirect();

        $customerCart = Cart::where('user_id', $customer->id)->first();
        $this->assertNotNull($customerCart);
        $this->assertSame(2, $customerCart->items()->count(), 'Logout must not delete the persistent cart.');

        // 5. Admin logs in and must NOT see Customer A's products.
        $this->actingAs($admin)
            ->get(route('cart.index'))
            ->assertOk()
            ->assertDontSee('Signature Headphones')
            ->assertDontSee('Smart Watch Pro');

        // Admin has their own (empty) cart — never a copy of anyone else's.
        $this->assertFalse(
            CartItem::whereHas('cart', fn ($q) => $q->where('user_id', $admin->id))
                ->whereIn('product_slug', ['signature-headphones', 'smart-watch-pro'])
                ->exists(),
            'Admin must never inherit another user\'s cart.'
        );

        // 6. Customer A logs back in — the cart is restored exactly.
        $this->actingAs($customer)
            ->get(route('cart.index'))
            ->assertOk()
            ->assertSee('Signature Headphones')
            ->assertSee('Smart Watch Pro');

        $restored = Cart::where('user_id', $customer->id)->first()->toLines();
        $this->assertCount(2, $restored);
        $this->assertArrayHasKey('signature-headphones', $restored);
        $this->assertArrayHasKey('smart-watch-pro', $restored);
    }

    public function test_guest_cart_merges_into_user_cart_on_login(): void
    {
        $customer = User::factory()->create(['account_type' => 'buyer', 'status' => 'active']);

        // A guest has items in the session cart…
        $guestLine = app(ProductCatalogService::class)->find('premium-backpack');
        $guestCart = [
            'premium-backpack' => [
                'product'          => 'premium-backpack',
                'title'            => $guestLine['title'],
                'image'            => $guestLine['image'],
                'price'            => 79.0,
                'quantity'         => 1,
                'selected_options' => [],
                'options_text'     => '',
                'sku'              => null,
                'variant_id'       => null,
            ],
        ];

        // …and logs in with that guest cart still in the session.
        $this->withSession(['cart' => $guestCart])
            ->post(route('login.post'), [
                'email'    => $customer->email,
                'password' => 'password',
            ])
            ->assertRedirect();

        // The guest line was merged into the persistent cart.
        $lines = Cart::where('user_id', $customer->id)->first()->toLines();
        $this->assertArrayHasKey('premium-backpack', $lines);

        // Adding the same product again increases quantity instead of duplicating.
        $this->actingAs($customer)
            ->post(route('cart.add', ['product' => 'premium-backpack']));

        $lines = Cart::where('user_id', $customer->id)->first()->toLines();
        $this->assertSame(2, (int) $lines['premium-backpack']['quantity']);
    }

    public function test_carts_are_isolated_between_two_customers(): void
    {
        $alice = User::factory()->create(['account_type' => 'buyer', 'status' => 'active']);
        $bob = User::factory()->create(['account_type' => 'buyer', 'status' => 'active']);

        $this->actingAs($alice)->post(route('cart.add', ['product' => 'smart-watch-pro']));
        $this->actingAs($bob)->post(route('cart.add', ['product' => 'signature-headphones']));

        $aliceSlugs = CartItem::whereHas('cart', fn ($q) => $q->where('user_id', $alice->id))
            ->pluck('product_slug')->all();
        $bobSlugs = CartItem::whereHas('cart', fn ($q) => $q->where('user_id', $bob->id))
            ->pluck('product_slug')->all();

        $this->assertEquals(['smart-watch-pro'], $aliceSlugs);
        $this->assertEquals(['signature-headphones'], $bobSlugs);
    }

    public function test_checkout_clears_only_the_purchasing_customers_cart(): void
    {
        $alice = User::factory()->create(['account_type' => 'buyer', 'status' => 'active']);
        $bob = User::factory()->create(['account_type' => 'buyer', 'status' => 'active']);

        $address = [
            'full_name'      => 'Alice Buyer',
            'phone'          => '9876543210',
            'house_number'   => '12/A',
            'street_address' => 'Rose Street',
            'city'           => 'Mumbai',
            'state'          => 'MH',
            'pincode'        => '400001',
            'country'        => 'India',
        ];

        // Alice adds a product; Bob adds his own product.
        $this->actingAs($alice)->post(route('cart.add', ['product' => 'smart-watch-pro']));
        $this->actingAs($bob)->post(route('cart.add', ['product' => 'signature-headphones']));

        // Alice checks out with a new address.
        $response = $this->actingAs($alice)->post(route('checkout.submit'), array_merge([
            'address_option'  => 'new',
            'new_address'     => $address,
            'shipping_method' => 'standard',
            'payment_method'  => 'cod',
        ]));
        $response->assertRedirect(route('checkout.complete'));

        // Only Alice's cart is cleared; Bob's cart is untouched.
        $this->assertSame(0, CartItem::whereHas('cart', fn ($q) => $q->where('user_id', $alice->id))->count());
        $this->assertSame(1, CartItem::whereHas('cart', fn ($q) => $q->where('user_id', $bob->id))->count());
        $this->assertSame(0, $bob->fresh()->orders()->count());
        $this->assertSame(1, $alice->fresh()->orders()->count());
    }
}
