<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\Order;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CouponTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::disk('local')->delete('custom_products_test.json');
    }

    private function admin(): User
    {
        return User::factory()->create(['account_type' => 'admin']);
    }

    private function buyer(): User
    {
        return User::factory()->create(['account_type' => 'buyer']);
    }

    private function coupon(array $attrs = []): Coupon
    {
        return Coupon::create(array_merge([
            'code' => 'SAVE10',
            'type' => 'fixed',
            'value' => 10,
            'active' => true,
        ], $attrs));
    }

    private function placeOrder(User $user, array $post = [])
    {
        return $this->actingAs($user)
            ->withSession(['cart' => [
                'smart-watch-pro' => ['title' => 'Smart Watch Pro', 'price' => 199.0, 'quantity' => 1],
            ]])
            ->post('/checkout', array_merge([
                'name' => 'Jane Doe',
                'phone' => '1234567890',
                'address' => '123 Main Street',
                'city' => 'New York',
                'state' => 'NY',
                'pincode' => '10001',
            ], $post));
    }

    public function test_non_admin_is_redirected_from_coupon_management()
    {
        $response = $this->actingAs($this->buyer())->get('/admin/coupons');
        $response->assertStatus(403);
    }

    public function test_admin_can_list_create_and_delete_coupons(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->get('/admin/coupons')->assertOk();

        $this->actingAs($admin)->post('/admin/coupons', [
            'code' => 'WELCOME5', 'type' => 'percent', 'value' => 5, 'active' => '1',
        ])->assertRedirect();
        $this->assertTrue(Coupon::where('code', 'WELCOME5')->exists());

        $coupon = Coupon::where('code', 'WELCOME5')->first();
        $this->actingAs($admin)->post('/admin/coupons/' . $coupon->id, ['_method' => 'DELETE'])->assertRedirect();
        $this->assertFalse(Coupon::where('code', 'WELCOME5')->exists());
    }

    public function test_valid_fixed_coupon_reduces_the_order_total(): void
    {
        $this->coupon(['code' => 'SAVE10', 'type' => 'fixed', 'value' => 10]);
        $buyer = $this->buyer();

        $this->placeOrder($buyer, ['coupon_code' => 'SAVE10'])->assertRedirect('/checkout/complete');

        $order = Order::where('user_id', $buyer->id)->first();
        $this->assertNotNull($order);
        $this->assertSame(199.0, (float) $order->subtotal);
        $this->assertSame(10.0, (float) $order->discount_amount);
        $this->assertSame('SAVE10', $order->coupon_code);
        $this->assertSame(189.0, (float) $order->total);
    }

    public function test_percent_coupon_applies_a_percentage_discount(): void
    {
        $this->coupon(['code' => 'TENOFF', 'type' => 'percent', 'value' => 10]);
        $this->placeOrder($this->buyer(), ['coupon_code' => 'TENOFF']);

        $order = Order::where('coupon_code', 'TENOFF')->first();
        $this->assertSame(19.9, (float) $order->discount_amount);
        $this->assertSame(179.1, (float) $order->total);
    }

    public function test_expired_coupon_is_ignored(): void
    {
        $this->coupon(['code' => 'OLDPASS', 'value' => 10, 'expires_at' => today()->subDays(1)]);
        $this->placeOrder($this->buyer(), ['coupon_code' => 'OLDPASS']);

        $order = Order::first();
        $this->assertSame(0.0, (float) $order->discount_amount);
        $this->assertSame(199.0, (float) $order->total);
    }

    public function test_invalid_coupon_leaves_total_unchanged(): void
    {
        $this->placeOrder($this->buyer(), ['coupon_code' => 'NOT-A-CODE']);

        $order = Order::first();
        $this->assertSame(199.0, (float) $order->total);
        $this->assertNull($order->coupon_code);
    }

    public function test_discount_is_capped_at_the_subtotal(): void
    {
        $this->coupon(['code' => 'BIGFIX', 'type' => 'fixed', 'value' => 500]);
        $this->placeOrder($this->buyer(), ['coupon_code' => 'BIGFIX']);

        $this->assertSame(0.0, (float) Order::first()->total);
    }

    public function test_selected_payment_method_is_persisted(): void
    {
        $this->placeOrder($this->buyer(), ['payment_method' => 'upi']);

        $this->assertSame('UPI', Order::first()->payment_method);
    }

    public function test_product_listing_shows_approved_review_rating(): void
    {
        $buyer = $this->buyer();
        Review::create([
            'user_id' => $buyer->id,
            'product_slug' => 'smart-watch-pro',
            'rating' => 5,
            'comment' => 'Great watch!',
            'status' => 'approved',
        ]);
        Review::create([
            'user_id' => $this->buyer()->id,
            'product_slug' => 'smart-watch-pro',
            'rating' => 3,
            'comment' => 'Decent.',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($buyer)->get('/products');

        $response->assertOk();
        $response->assertSee('5.0');
        $response->assertSee('(1)');
    }
}