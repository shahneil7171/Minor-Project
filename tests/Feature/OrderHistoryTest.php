<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OrderHistoryTest extends TestCase
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

    private function placeOrder(User $user)
    {
        return $this->actingAs($user)
            ->withSession(['cart' => [
                'smart-watch-pro' => [
                    'product' => 'smart-watch-pro',
                    'title' => 'Smart Watch Pro',
                    'price' => 199.0,
                    'quantity' => 2,
                    'sku' => 'KDP-SMW-001',
                    'image' => 'https://images.unsplash.com/photo-1518444209757-9ae0b9eb3734?auto=format&fit=crop&w=800&q=80',
                ],
            ]])
            ->post('/checkout', [
                'address_option' => 'new',
                'shipping_method' => 'standard',
                'payment_method' => 'cod',
                'new_address' => [
                    'full_name' => 'Jane Doe',
                    'phone' => '1234567890',
                    'house_number' => '123',
                    'street_address' => 'Main Street',
                    'city' => 'New York',
                    'state' => 'NY',
                    'pincode' => '10001',
                    'country' => 'India',
                ],
            ]);
    }

    public function test_guest_is_redirected_from_order_history(): void
    {
        $response = $this->get('/orders');

        $response->assertRedirect('/login');
        $response->assertStatus(302);
    }

    public function test_checkout_persists_an_order_with_items(): void
    {
        $buyer = $this->buyer();

        $response = $this->placeOrder($buyer);

        $response->assertRedirect('/checkout/complete');

        $order = Order::where('user_id', $buyer->id)->first();

        $this->assertNotNull($order);
        $this->assertStringContainsString('KDP-', $order->order_number);
        $this->assertSame('pending', $order->status);
        $this->assertSame(2, $order->items()->sum('quantity'));
        $this->assertSame('Jane Doe', $order->shipping_name);
    }

    public function test_order_history_lists_the_users_orders(): void
    {
        $buyer = $this->buyer();
        $this->placeOrder($buyer);

        $response = $this->actingAs($buyer)->get('/orders');

        $response->assertOk();
        $response->assertViewIs('orders.index');
    }

    public function test_order_detail_supports_tracking(): void
    {
        $buyer = $this->buyer();
        $this->placeOrder($buyer);

        $order = Order::where('user_id', $buyer->id)->first();

        $response = $this->actingAs($buyer)->get('/orders/' . $order->id);

        $response->assertOk();
        $response->assertViewIs('orders.show');
    }

    public function test_a_user_cannot_view_someone_elses_order(): void
    {
        $buyer = $this->buyer();
        $other = $this->buyer();

        $this->placeOrder($buyer);

        $order = Order::where('user_id', $buyer->id)->first();

        $response = $this->actingAs($other)->get('/orders/' . $order->id);

        $response->assertStatus(403);
    }

    public function test_admin_can_update_an_order_status(): void
    {
        $buyer = $this->buyer();
        $this->placeOrder($buyer);
        $order = Order::where('user_id', $buyer->id)->first();

        $admin = User::factory()->create(['account_type' => 'admin']);

        $response = $this->actingAs($admin)
            ->post('/admin/orders/' . $order->id . '/status', ['status' => 'shipped']);

        $response->assertRedirect();

        $order->refresh();
        $this->assertSame('shipped', $order->status);
    }

    public function test_buyer_cannot_update_an_order_status(): void
    {
        $buyer = $this->buyer();
        $this->placeOrder($buyer);
        $order = Order::where('user_id', $buyer->id)->first();

        $response = $this->actingAs($buyer)
            ->post('/admin/orders/' . $order->id . '/status', ['status' => 'shipped']);

                $response->assertStatus(403);
    }

    /* -----------------------------------------------------------------
     | Point 11 — Checkout completeness tests
     * --------------------------------------------------------------- */

    private function demoCart(): array
    {
        return [
            'smart-watch-pro' => [
                'product'   => 'smart-watch-pro',
                'title'     => 'Smart Watch Pro',
                'price'     => 199.0,
                'quantity'  => 1,
                'sku'       => 'KDP-SMW-001',
                'image'     => 'https://images.unsplash.com/photo-1518444209757-9ae0b9eb3734?auto=format&fit=crop&w=800&q=80',
            ],
        ];
    }

    private function guestCheckoutFields(): array
    {
        return [
            'address_option'    => 'guest',
            'customer_name'     => 'Guest User',
            'customer_email'    => 'guest@example.com',
            'customer_phone'    => '1234567890',
            'shipping_address'  => '123 Main Street',
            'shipping_city'     => 'New York',
            'shipping_state'    => 'NY',
            'shipping_pincode'  => '10001',
            'shipping_country'  => 'India',
            'shipping_method'   => 'standard',
            'payment_method'    => 'cod',
        ];
    }

    public function test_guest_can_complete_checkout_without_an_account(): void
    {
        // Expected: 199 (subtotal) + 35.82 (18 % tax) + 50 (shipping) = 284.82
        $response = $this->withSession(['cart' => $this->demoCart()])
            ->post('/checkout', $this->guestCheckoutFields());

        $response->assertRedirect('/checkout/complete');

        $order = Order::where('customer_email', 'guest@example.com')->first();
        $this->assertNotNull($order);
        $this->assertNull($order->user_id);
        $this->assertSame('Guest User', $order->shipping_name);
        $this->assertSame('Cash on Delivery', $order->payment_method);
        $this->assertSame('Standard Delivery', $order->shipping_method);
        $this->assertSame('pending', $order->status);
        $this->assertSame(284.82, (float) $order->total);
        $this->assertSame(1, $order->items()->count());
    }

    public function test_guest_checkout_creates_order_item_with_snapshot(): void
    {
        $this->withSession(['cart' => $this->demoCart()])
            ->post('/checkout', $this->guestCheckoutFields());

        $order = Order::where('customer_email', 'guest@example.com')->first();
        $item  = $order->items()->first();

        $this->assertSame('smart-watch-pro', $item->product_slug);
        $this->assertSame('Smart Watch Pro', $item->product_title);
        $this->assertSame(199.0, (float) $item->price);
        $this->assertSame(1, (int) $item->quantity);
        $this->assertSame(199.0, (float) $item->subtotal);
    }

    public function test_cart_is_cleared_after_successful_order(): void
    {
        $buyer = $this->buyer();
        $this->placeOrder($buyer);

        $this->assertNull(session('cart'));
        $this->assertNotNull(session('checkout_order_id'));
    }

    public function test_failed_checkout_does_not_clear_cart(): void
    {
        $buyer = $this->buyer();

        // Missing required payment_method → validation should fail.
        $response = $this->actingAs($buyer)
            ->withSession(['cart' => $this->demoCart()])
            ->post('/checkout', [
                'address_option' => 'new',
                'shipping_method' => 'standard',
                'new_address' => [
                    'full_name'      => 'Jane Doe',
                    'phone'          => '1234567890',
                    'house_number'   => '123',
                    'street_address' => 'Main Street',
                    'city'           => 'New York',
                    'state'          => 'NY',
                    'pincode'        => '10001',
                    'country'        => 'India',
                ],
            ]);

        $response->assertSessionHasErrors('payment_method');
        $this->assertNotEmpty(session('cart'));
                $this->assertNull(Order::where('user_id', $buyer->id)->first());
    }

    public function test_registered_user_can_checkout_with_a_saved_address(): void
    {
        $buyer = $this->buyer();

        $address = Address::create([
            'user_id'             => $buyer->id,
            'full_name'           => 'Saved Person',
            'phone'               => '9876543210',
            'house_number'        => '456',
            'street_address'      => 'Oak Avenue',
            'city'                => 'Boston',
            'state'               => 'MA',
            'pincode'             => '02108',
            'country'             => 'United States',
            'is_default_shipping' => true,
            'is_default_billing'  => true,
        ]);

        $response = $this->actingAs($buyer)
            ->withSession(['cart' => $this->demoCart()])
            ->post('/checkout', [
                'address_option'  => 'saved',
                'address_id'      => $address->id,
                'shipping_method' => 'standard',
                'payment_method'  => 'upi',
                'payment_confirmation' => '1',
            ]);

        $response->assertRedirect('/checkout/complete');

        $order = Order::where('user_id', $buyer->id)->first();
        $this->assertNotNull($order);
        $this->assertSame('Saved Person', $order->shipping_name);
        $this->assertSame('9876543210', $order->shipping_phone);
        $this->assertSame('Boston', $order->shipping_city);
        $this->assertSame('UPI', $order->payment_method);
        $this->assertSame('pending', $order->status);
    }

    public function test_new_address_is_saved_to_the_users_address_book(): void
    {
        $buyer = $this->buyer();
        $this->assertCount(0, $buyer->addresses);

        $this->placeOrder($buyer);

        $buyer->refresh();
        $this->assertCount(1, $buyer->addresses);
        $address = $buyer->addresses()->first();
        $this->assertSame('Jane Doe', $address->full_name);
        $this->assertTrue($address->is_default_shipping);
    }

    public function test_user_cannot_select_someone_elses_address(): void
    {
        $buyer = $this->buyer();
        $other = $this->buyer();

        $otherAddress = Address::create([
            'user_id'             => $other->id,
            'full_name'           => 'Other User',
            'phone'               => '5555555555',
            'house_number'        => '1',
            'street_address'      => 'Secret St',
            'city'                => 'Hidden',
            'state'               => 'HV',
            'pincode'             => '00000',
            'country'             => 'India',
        ]);

        $response = $this->actingAs($buyer)
            ->withSession(['cart' => $this->demoCart()])
            ->post('/checkout', [
                'address_option'  => 'saved',
                'address_id'      => $otherAddress->id,
                'shipping_method' => 'standard',
                'payment_method'  => 'cod',
            ]);

        $response->assertSessionHasErrors('address_id');
        $this->assertNull(Order::where('user_id', $buyer->id)->first());
    }

    public function test_guest_can_access_cart_and_checkout_pages(): void
    {
        // A guest must be able to browse the cart and reach checkout.
        $cart = $this->withSession(['cart' => $this->demoCart()])->get('/cart');
        $cart->assertOk();
        $cart->assertSee('Continue as Guest');

        $checkout = $this->withSession(['cart' => $this->demoCart()])->get('/checkout');
        $checkout->assertOk();
        $checkout->assertViewIs('checkout');
        $checkout->assertSee('Checkout');
    }

    public function test_registered_user_sees_saved_addresses_on_checkout_page(): void
    {
        $buyer = $this->buyer();

        Address::create([
            'user_id'             => $buyer->id,
            'full_name'           => 'Home Person',
            'phone'               => '1112223333',
            'house_number'        => '10',
            'street_address'      => 'Home Lane',
            'city'                => 'Delhi',
            'state'               => 'DL',
            'pincode'             => '110001',
            'country'             => 'India',
        ]);

        $response = $this->actingAs($buyer)
            ->withSession(['cart' => $this->demoCart()])
            ->get('/checkout');

        $response->assertOk();
        $response->assertSee('Home Person');
        $response->assertSee('address_option');
        $response->assertSee('saved');
    }

    public function test_checkout_complete_shows_track_order_and_my_orders_for_auth_user(): void
    {
        $buyer = $this->buyer();
        $this->placeOrder($buyer);

        $response = $this->actingAs($buyer)->get('/checkout/complete');

        $response->assertOk();
        $response->assertSee('Track order');
        $response->assertSee('My Orders');
        $response->assertSee('Continue shopping');
    }

    public function test_guests_cannot_view_wishlist_or_orders(): void
    {
        $wishlist = $this->get('/wishlist');
        $wishlist->assertRedirect('/login');

        $orders = $this->get('/orders');
        $orders->assertRedirect('/login');
    }

    /* -----------------------------------------------------------------
     | Point 13 — Order Status System tests
     * --------------------------------------------------------------- */

    public function test_new_order_starts_with_pending_status(): void
    {
        $buyer = $this->buyer();
        $this->placeOrder($buyer);

        $order = Order::where('user_id', $buyer->id)->first();
        $this->assertNotNull($order);
        $this->assertSame('pending', $order->status);
        $this->assertSame('Pending', $order->statusLabel());
    }

    public function test_admin_can_move_status_through_full_lifecycle(): void
    {
        $buyer = $this->buyer();
        $this->placeOrder($buyer);
        $order = Order::where('user_id', $buyer->id)->first();
        $admin = User::factory()->create(['account_type' => 'admin']);

        // pending -> processing
        $this->actingAs($admin)->post('/admin/orders/' . $order->id . '/status', ['status' => 'processing']);
        $this->assertSame('processing', $order->fresh()->status);

        // processing -> packed
        $this->actingAs($admin)->post('/admin/orders/' . $order->id . '/status', ['status' => 'packed']);
        $this->assertSame('packed', $order->fresh()->status);

        // packed -> shipped
        $this->actingAs($admin)->post('/admin/orders/' . $order->id . '/status', ['status' => 'shipped']);
        $this->assertSame('shipped', $order->fresh()->status);

        // shipped -> delivered
        $this->actingAs($admin)->post('/admin/orders/' . $order->id . '/status', ['status' => 'delivered']);
        $this->assertSame('delivered', $order->fresh()->status);
    }

    public function test_admin_can_cancel_a_pending_order(): void
    {
        $buyer = $this->buyer();
        $this->placeOrder($buyer);
        $order = Order::where('user_id', $buyer->id)->first();
        $admin = User::factory()->create(['account_type' => 'admin']);

        $response = $this->actingAs($admin)
            ->post('/admin/orders/' . $order->id . '/status', ['status' => 'cancelled']);

        $response->assertRedirect();
        $this->assertTrue($order->fresh()->isCancelled());
    }

    public function test_invalid_status_transition_is_rejected(): void
    {
        // An order already delivered must not move backwards to pending.
        $buyer = $this->buyer();
        $this->placeOrder($buyer);
        $order = Order::where('user_id', $buyer->id)->first();
        $admin = User::factory()->create(['account_type' => 'admin']);

        $this->actingAs($admin)->post('/admin/orders/' . $order->id . '/status', ['status' => 'delivered']);
        $this->assertSame('delivered', $order->fresh()->status);

        // delivered -> pending should be rejected server-side.
        $response = $this->actingAs($admin)
            ->post('/admin/orders/' . $order->id . '/status', ['status' => 'pending']);

        $response->assertSessionHas('error');
        $this->assertSame('delivered', $order->fresh()->status);
    }

    public function test_invalid_status_value_is_rejected(): void
    {
        $buyer = $this->buyer();
        $this->placeOrder($buyer);
        $order = Order::where('user_id', $buyer->id)->first();
        $admin = User::factory()->create(['account_type' => 'admin']);

        $response = $this->actingAs($admin)
            ->post('/admin/orders/' . $order->id . '/status', ['status' => 'not-a-status']);

        $response->assertSessionHasErrors('status');
        $this->assertSame('pending', $order->fresh()->status);
    }

    public function test_cancelled_order_cannot_be_advanced(): void
    {
        $buyer = $this->buyer();
        $this->placeOrder($buyer);
        $order = Order::where('user_id', $buyer->id)->first();
        $admin = User::factory()->create(['account_type' => 'admin']);

        $this->actingAs($admin)->post('/admin/orders/' . $order->id . '/status', ['status' => 'cancelled']);
        $this->assertTrue($order->fresh()->isCancelled());

        $response = $this->actingAs($admin)
            ->post('/admin/orders/' . $order->id . '/status', ['status' => 'delivered']);

        $response->assertSessionHas('error');
        $this->assertTrue($order->fresh()->isCancelled());
    }

    public function test_customer_sees_updated_status_in_order_details(): void
    {
        $buyer = $this->buyer();
        $this->placeOrder($buyer);
        $order = Order::where('user_id', $buyer->id)->first();
        $admin = User::factory()->create(['account_type' => 'admin']);

        $this->actingAs($admin)->post('/admin/orders/' . $order->id . '/status', ['status' => 'packed']);

        $response = $this->actingAs($buyer)->get('/orders/' . $order->id);

        $response->assertOk();
        $response->assertSee('Packed');
        $response->assertSee('pending');
        $response->assertSee('processing');
        $response->assertSee('packed');
    }

    public function test_my_orders_filter_by_status(): void
    {
        $buyer = $this->buyer();
        $this->placeOrder($buyer);
        $order = Order::where('user_id', $buyer->id)->first();
        $admin = User::factory()->create(['account_type' => 'admin']);
        $this->actingAs($admin)->post('/admin/orders/' . $order->id . '/status', ['status' => 'shipped']);

        $response = $this->actingAs($buyer)->get('/orders?status=shipped');

        $response->assertOk();
        $response->assertViewIs('orders.index');
        $response->assertSee('Shipped');
    }

    public function test_admin_order_page_lists_statuses_and_filters(): void
    {
        $buyer = $this->buyer();
        $this->placeOrder($buyer);
        $admin = User::factory()->create(['account_type' => 'admin']);

        // Full list includes all status labels including Packed.
        $all = $this->actingAs($admin)->get('/admin/orders');
        $all->assertOk();
        $all->assertViewIs('admin.orders.index');
        $all->assertSee('Packed');
        $all->assertSee('Processing');
        $all->assertSee('Shipped');
        $all->assertSee('Delivered');
        $all->assertSee('Cancelled');

        // Status filter works.
        $filtered = $this->actingAs($admin)->get('/admin/orders?status=pending');
        $filtered->assertOk();
        $filtered->assertSee('Pending');
    }

    public function test_non_admin_is_blocked_from_admin_order_page(): void
    {
        $buyer = $this->buyer();

        $response = $this->actingAs($buyer)->get('/admin/orders');

        $response->assertStatus(403);
    }
}