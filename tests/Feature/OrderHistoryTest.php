<?php

namespace Tests\Feature;

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
                    'title' => 'Smart Watch Pro',
                    'price' => 199.0,
                    'quantity' => 2,
                ],
            ]])
            ->post('/checkout', [
                'name' => 'Jane Doe',
                'phone' => '1234567890',
                'address' => '123 Main Street',
                'city' => 'New York',
                'state' => 'NY',
                'pincode' => '10001',
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
}