<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use App\Services\PaymentGatewayFactory;
use App\Services\Gateways\CashfreeGateway;
use App\Services\Gateways\RazorpayGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DualGatewayTest extends TestCase
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

    private function checkout(User $user, string $paymentMethod): \Illuminate\Testing\TestResponse
    {
        $this->actingAs($user);

        app(\App\Services\CartService::class)->save([
            'smart-watch-pro' => [
                'product' => 'smart-watch-pro',
                'title' => 'Smart Watch Pro',
                'price' => 199.0,
                'quantity' => 1,
                'sku' => 'KDP-SMW-001',
                'image' => 'https://images.unsplash.com/photo-1518444209757-9ae0b9eb3734?auto=format&fit=crop&w=800&q=80',
            ],
        ]);

        return $this->post('/checkout', [
            'address_option' => 'new',
            'shipping_method' => 'standard',
            'payment_method' => $paymentMethod,
            'payment_confirmation' => '1',
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

    public function test_cashfree_checkout_creates_pending_gateway_order(): void
    {
        $buyer = $this->buyer();
        $this->checkout($buyer, 'cashfree')->assertRedirect('/payments/' . $buyer->id);
        $order = Order::where('user_id', $buyer->id)->firstOrFail();
        $this->assertSame('cashfree', $order->payment_provider);
        $this->assertSame('pending', $order->payment_status);
        $this->assertNull($order->cashfree_order_id);
        $this->assertNull($order->cashfree_payment_id);
        $this->assertTrue($order->isGateway());
        $this->assertTrue($order->isCashfree());
    }

    public function test_razorpay_checkout_creates_pending_gateway_order(): void
    {
        $buyer = $this->buyer();
        $this->checkout($buyer, 'razorpay')->assertRedirect('/payments/' . $buyer->id);
        $order = Order::where('user_id', $buyer->id)->firstOrFail();
        $this->assertSame('razorpay', $order->payment_provider);
        $this->assertSame('pending', $order->payment_status);
        $this->assertTrue($order->isGateway());
        $this->assertFalse($order->isCashfree());
    }

    public function test_cod_checkout_keeps_payment_provider_null(): void
    {
        $buyer = $this->buyer();
        $this->checkout($buyer, 'cod')->assertRedirect('/checkout/complete');
        $order = Order::where('user_id', $buyer->id)->firstOrFail();
        $this->assertNull($order->payment_provider);
        $this->assertFalse($order->isGateway());
        $this->assertFalse($order->isCashfree());
        $this->assertSame('Cash on Delivery', $order->payment_method);
    }

    public function test_mark_payment_captured_routes_to_provider_column(): void
    {
        $buyer = $this->buyer();

        $cashfreeOrder = Order::create([
            'user_id' => $buyer->id, 'customer_email' => 'test@example.com',
            'order_number' => 'TEST-001', 'status' => 'pending',
            'subtotal' => 250.00, 'tax' => 0.00, 'shipping_cost' => 0.00,
            'total' => 250.00, 'discount_amount' => 0.00,
            'payment_method' => 'Online Payment', 'payment_provider' => 'cashfree',
            'payment_status' => 'pending', 'shipping_name' => 'Test User',
            'shipping_phone' => '1234567890', 'shipping_address' => '123 Main St',
            'shipping_city' => 'New York', 'shipping_state' => 'NY',
            'shipping_pincode' => '10001', 'shipping_country' => 'USA',
        ]);

        $this->assertTrue($cashfreeOrder->markPaymentCaptured('cf_pay_123'));
        $this->assertSame('cf_pay_123', $cashfreeOrder->fresh()->cashfree_payment_id);
        $this->assertSame('captured', $cashfreeOrder->fresh()->payment_status);
        $this->assertNull($cashfreeOrder->fresh()->razorpay_payment_id);

        $razorpayOrder = Order::create([
            'user_id' => $buyer->id, 'customer_email' => 'test@example.com',
            'order_number' => 'TEST-002', 'status' => 'pending',
            'subtotal' => 300.00, 'tax' => 0.00, 'shipping_cost' => 0.00,
            'total' => 300.00, 'discount_amount' => 0.00,
            'payment_method' => 'Online Payment', 'payment_provider' => 'razorpay',
            'payment_status' => 'pending', 'shipping_name' => 'Test User',
            'shipping_phone' => '1234567890', 'shipping_address' => '123 Main St',
            'shipping_city' => 'New York', 'shipping_state' => 'NY',
            'shipping_pincode' => '10001', 'shipping_country' => 'USA',
        ]);

        $this->assertTrue($razorpayOrder->markPaymentCaptured('rzp_pay_456'));
        $this->assertSame('rzp_pay_456', $razorpayOrder->fresh()->razorpay_payment_id);
        $this->assertSame('captured', $razorpayOrder->fresh()->payment_status);
        $this->assertNull($razorpayOrder->fresh()->cashfree_payment_id);
    }

    public function test_mark_payment_captured_is_idempotent(): void
    {
        $buyer = $this->buyer();

        $order = Order::create([
            'user_id' => $buyer->id, 'customer_email' => 'test@example.com',
            'order_number' => 'TEST-003', 'status' => 'pending',
            'subtotal' => 100.00, 'tax' => 0.00, 'shipping_cost' => 0.00,
            'total' => 100.00, 'discount_amount' => 0.00,
            'payment_method' => 'Online Payment', 'payment_provider' => 'cashfree',
            'payment_status' => 'captured', 'cashfree_payment_id' => 'cf_pay_existing',
            'shipping_name' => 'Test User', 'shipping_phone' => '1234567890',
            'shipping_address' => '123 Main St', 'shipping_city' => 'New York',
            'shipping_state' => 'NY', 'shipping_pincode' => '10001',
            'shipping_country' => 'USA',
        ]);

        $this->assertFalse($order->markPaymentCaptured('cf_pay_existing'));
    }

    public function test_mark_payment_failed_sets_failed_status(): void
    {
        $buyer = $this->buyer();

        $order = Order::create([
            'user_id' => $buyer->id, 'customer_email' => 'test@example.com',
            'order_number' => 'TEST-004', 'status' => 'pending',
            'subtotal' => 150.00, 'tax' => 0.00, 'shipping_cost' => 0.00,
            'total' => 150.00, 'discount_amount' => 0.00,
            'payment_method' => 'Online Payment', 'payment_provider' => 'razorpay',
            'payment_status' => 'pending', 'shipping_name' => 'Test User',
            'shipping_phone' => '1234567890', 'shipping_address' => '123 Main St',
            'shipping_city' => 'New York', 'shipping_state' => 'NY',
            'shipping_pincode' => '10001', 'shipping_country' => 'USA',
        ]);

        $order->markPaymentFailed();
        $this->assertSame('failed', $order->fresh()->payment_status);
    }

    public function test_checkout_rejects_unknown_payment_method(): void
    {
        $buyer = $this->buyer();
        app(\App\Services\CartService::class)->save([
            'smart-watch-pro' => [
                'product' => 'smart-watch-pro', 'title' => 'Smart Watch Pro',
                'price' => 199.0, 'quantity' => 1, 'sku' => 'KDP-SMW-001',
                'image' => 'x.jpg',
            ],
        ]);
        $this->post('/checkout', [
            'address_option' => 'new', 'shipping_method' => 'standard',
            'payment_method' => 'nonexistent',
            'new_address' => ['full_name' => 'Jane Doe', 'phone' => '1234567890',
                'house_number' => '123', 'street_address' => 'Main Street',
                'city' => 'New York', 'state' => 'NY', 'pincode' => '10001',
                'country' => 'India'],
        ])->assertSessionHasErrors('payment_method');
        $this->assertSame(0, Order::where('user_id', $buyer->id)->count());
    }

    public function test_factory_for_returns_razorpay_gateway(): void
    {
        $gateway = PaymentGatewayFactory::for('razorpay');
        $this->assertInstanceOf(RazorpayGateway::class, $gateway);
        $this->assertSame('razorpay', $gateway->name());
    }

    public function test_factory_for_returns_cashfree_gateway(): void
    {
        $gateway = PaymentGatewayFactory::for('cashfree');
        $this->assertInstanceOf(CashfreeGateway::class, $gateway);
        $this->assertSame('cashfree', $gateway->name());
    }

    public function test_factory_for_unknown_provider_throws(): void
    {
        $this->expectException(ValidationException::class);
        PaymentGatewayFactory::for('nonexistent');
    }

    public function test_factory_for_order_returns_correct_gateway(): void
    {
        $buyer = $this->buyer();

        $cashfreeOrder = Order::create([
            'user_id' => $buyer->id, 'customer_email' => 'test@example.com',
            'order_number' => 'TEST-005', 'status' => 'pending',
            'subtotal' => 100.00, 'tax' => 0.00, 'shipping_cost' => 0.00,
            'total' => 100.00, 'discount_amount' => 0.00,
            'payment_method' => 'Online Payment', 'payment_provider' => 'cashfree',
            'payment_status' => 'pending', 'shipping_name' => 'Test User',
            'shipping_phone' => '1234567890', 'shipping_address' => '123 Main St',
            'shipping_city' => 'New York', 'shipping_state' => 'NY',
            'shipping_pincode' => '10001', 'shipping_country' => 'USA',
        ]);
        $razorpayOrder = Order::create([
            'user_id' => $buyer->id, 'customer_email' => 'test@example.com',
            'order_number' => 'TEST-006', 'status' => 'pending',
            'subtotal' => 100.00, 'tax' => 0.00, 'shipping_cost' => 0.00,
            'total' => 100.00, 'discount_amount' => 0.00,
            'payment_method' => 'Online Payment', 'payment_provider' => 'razorpay',
            'payment_status' => 'pending', 'shipping_name' => 'Test User',
            'shipping_phone' => '1234567890', 'shipping_address' => '123 Main St',
            'shipping_city' => 'New York', 'shipping_state' => 'NY',
            'shipping_pincode' => '10001', 'shipping_country' => 'USA',
        ]);
        $this->assertInstanceOf(CashfreeGateway::class, PaymentGatewayFactory::forOrder($cashfreeOrder));
        $this->assertInstanceOf(RazorpayGateway::class, PaymentGatewayFactory::forOrder($razorpayOrder));
    }

    public function test_razorpay_verify_signature_accepts_valid_signature(): void
    {
        config(['razorpay.key_secret' => 'test_secret_123']);
        $gateway = new RazorpayGateway();
        $expected = hash_hmac('sha256', 'order_test123|pay_test456', 'test_secret_123');
        $gateway->verifySignature('order_test123', 'pay_test456', $expected);
        $this->assertTrue(true);
    }

    public function test_razorpay_verify_signature_rejects_invalid_signature(): void
    {
        config(['razorpay.key_secret' => 'test_secret_123']);
        $gateway = new RazorpayGateway();
        $this->expectException(ValidationException::class);
        $gateway->verifySignature('order_test123', 'pay_test456', 'tampered_signature');
    }
}