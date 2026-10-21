<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use App\Services\Gateways\CashfreeGateway;
use App\Services\PaymentGatewayFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;

class CashfreePaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['cashfree.key_id' => 'test_key_id', 'cashfree.key_secret' => 'test_secret']);

        Cache::flush();
        Mail::fake();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function buyer(): User
    {
        return User::factory()->create(['account_type' => 'buyer']);
    }

    private function createCashfreeOrder(User $user, float $total = 199.00, string $paymentStatus = 'pending'): Order
    {
        return Order::create([
            'user_id' => $user->id,
            'customer_email' => 'buyer@example.com',
            'order_number' => 'TEST-CF-' . uniqid(),
            'status' => 'pending',
            'subtotal' => $total,
            'tax' => 0.00,
            'shipping_cost' => 0.00,
            'total' => $total,
            'discount_amount' => 0.00,
            'payment_method' => 'Online Payment',
            'payment_provider' => 'cashfree',
            'payment_status' => $paymentStatus,
            'cashfree_order_id' => 'order_' . 'TEST-CF-' . uniqid(),
            'shipping_name' => 'Buyer Name',
            'shipping_phone' => '1234567890',
            'shipping_address' => '123 Main St',
            'shipping_city' => 'New York',
            'shipping_state' => 'NY',
            'shipping_pincode' => '10001',
            'shipping_country' => 'USA',
        ]);
    }

    private function makeWebhookSignature(string $payload, int $timestamp, string $secret): string
    {
        return base64_encode(hash_hmac('sha256', $timestamp . $payload, $secret, true));
    }

    private function mockCashfreeGateway(array $verifyReturn = [], ?\Throwable $verifyThrow = null, array $webhookReturn = [], ?\Throwable $webhookThrow = null): void
    {
        $mock = Mockery::mock('overload:App/Services/Gateways/CashfreeGateway');

        if ($verifyThrow) {
            $mock->shouldReceive('verify')->andThrow($verifyThrow);
        } else {
            $mock->shouldReceive('verify')->andReturn($verifyReturn);
        }

        if ($webhookThrow) {
            $mock->shouldReceive('handleWebhook')->andThrow($webhookThrow);
        } else {
            $mock->shouldReceive('handleWebhook')->andReturn($webhookReturn);
        }

public function test_cashfree_return_successful_payment_confirms_order(): void
    {
        $buyer = $this->buyer();
        $order = $this->createCashfreeOrder($buyer, 199.00);

        $this->mockCashfreeGateway([
            'payment_id' => 'cf_pay_123',
            'status' => 'captured',
        ]);

        $this->actingAs($buyer)
            ->get(route('payments.cashfree.return', ['order_id' => $order->cashfree_order_id, 'cf_payment_id' => 'cf_pay_123']))
            ->assertRedirect(route('checkout.complete'));

        $order->refresh();
        $this->assertSame('captured', $order->payment_status);
        $this->assertSame('cf_pay_123', $order->cashfree_payment_id);
        $this->assertSame('confirmed', $order->status);
    }
        $mock->shouldReceive('name')->andReturn('cashfree');
        $mock->shouldReceive('statusForWebhook')->andReturnUsing(function ($eventType) {
            return match ($eventType) {
                'order.paid', 'payment.captured' => 'captured',
                'order.failed' => 'failed',
                default => null,
            };
        });
    }
}