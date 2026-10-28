<?php

namespace Tests\Feature;

use App\Mail\OrderConfirmationMail;
use App\Models\InventoryTransaction;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\PaymentAttempt;
use App\Models\Product;
use App\Models\User;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PaymentAttemptTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    private function buyer(): User
    {
        return User::factory()->create(['account_type' => 'buyer']);
    }

    private function checkout(User $user, string $paymentMethod, array $overrides = []): \Illuminate\Testing\TestResponse
    {
        $this->actingAs($user);

        app(CartService::class)->save([
            'smart-watch-pro' => [
                'product' => 'smart-watch-pro',
                'title' => 'Smart Watch Pro',
                'price' => 199.0,
                'quantity' => 1,
                'sku' => 'KDP-SMW-001',
                'image' => 'x.jpg',
            ],
        ]);

        $data = array_merge([
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
        ], $overrides);

        if ($paymentMethod === 'card') {
            $data['card_name'] = 'Jane Doe';
            $data['card_number'] = '4111 1111 1111 1111';
            $data['card_expiry'] = '12/30';
            $data['card_cvv'] = '123';
        }

        return $this->actingAs($user)->post('/checkout', $data);
    }

    private function assertPaymentConfirmed(Order $order, PaymentAttempt $attempt): void
    {
        $order->refresh();
        $attempt->refresh();

        $this->assertTrue($order->isConfirmed(), 'A verified payment must confirm the order.');
        $this->assertSame('success', $attempt->status);
        $this->assertNotNull($attempt->provider_reference, 'A verified payment needs a transaction reference.');
        $this->assertNotNull($attempt->verified_at);
        $this->assertSame((float) $order->total, (float) $attempt->amount);

        Mail::assertSent(OrderConfirmationMail::class);
    }

    public function test_upi_selection_does_not_confirm_the_order(): void
    {
        $buyer = $this->buyer();
        Date::setTestNow(Carbon::parse('2026-10-28 10:00:00'));

        $this->checkout($buyer, 'upi')->assertStatus(302);

        $order = Order::where('user_id', $buyer->id)->firstOrFail();
        $this->assertSame('pending', $order->status);
        $this->assertSame('pending', $order->payment_status);

        $attempt = PaymentAttempt::where('order_id', $order->id)->firstOrFail();
        $this->assertSame('pending', $attempt->status);
        $this->assertSame('upi', $attempt->payment_method);
        $this->assertSame((float) $order->total, (float) $attempt->amount);
        $this->assertSame('INR', $attempt->currency);
        $this->assertSame('2026-10-28 10:05:00', $attempt->expires_at->format('Y-m-d H:i:s'));

        $this->get(route('payment.attempt.show', ['token' => $attempt->token]))
            ->assertOk()
            ->assertSee('id="countdown"', false)
            ->assertSee('data-deadline', false);

        $this->get(route('payment.attempt.show', ['token' => $attempt->token]))
            ->assertOk()
            ->assertSee('Manual UPI payment', false);

        // A (simulated) pay attempt on a manual UPI session is rejected.
        $this->post(route('payment.attempt.pay', ['token' => $attempt->token]))
            ->assertRedirect(route('payment.attempt.show', ['token' => $attempt->token]));

        $attempt->refresh();
        $this->assertSame('pending', $attempt->status, 'Manual UPI must never be auto-confirmed.');
        $order->refresh();
        $this->assertSame('pending', $order->status);
        $this->assertFalse($order->isConfirmed());

        Date::setTestNow();
    }

    public function test_successful_card_payment_confirms_an_order(): void
    {
        $buyer = $this->buyer();

        $this->checkout($buyer, 'card')->assertStatus(302);

        $order = Order::where('user_id', $buyer->id)->firstOrFail();
        $attempt = PaymentAttempt::where('order_id', $order->id)->firstOrFail();

        $this->assertSame('card', $attempt->payment_method);
        $this->assertSame('pending', $attempt->status);

        $this->post(route('payment.attempt.pay', ['token' => $attempt->token]))
            ->assertRedirect(route('checkout.complete'));

        $this->assertPaymentConfirmed($order, $attempt);

        $raw = json_encode($order->getAttributes());
        $this->assertStringNotContainsString('4111111111111111', $raw);
        $this->assertStringNotContainsString('"card_cvv"', $raw);
    }

    public function test_failed_payment_does_not_confirm_an_order(): void
    {
        $buyer = $this->buyer();

        $this->checkout($buyer, 'upi')->assertStatus(302);

        $order = Order::where('user_id', $buyer->id)->firstOrFail();
        $attempt = PaymentAttempt::where('order_id', $order->id)->firstOrFail();

        $this->post(route('payment.attempt.fail', ['token' => $attempt->token]))
            ->assertRedirect(route('payment.attempt.show', ['token' => $attempt->token]));

        $attempt->refresh();
        $this->assertSame('failed', $attempt->status);

        $order->refresh();
        $this->assertSame('pending', $order->status, 'A failed payment must not confirm the order.');

        $this->post(route('payment.attempt.pay', ['token' => $attempt->token]))
            ->assertRedirect(route('payment.attempt.show', ['token' => $attempt->token]));

        $attempt->refresh();
        $this->assertSame('failed', $attempt->status);
        $this->assertSame('pending', Order::where('user_id', $buyer->id)->value('status'));
    }

    public function test_pending_payment_does_not_confirm_an_order(): void
    {
        $buyer = $this->buyer();

        $this->checkout($buyer, 'upi')->assertStatus(302);

        $order = Order::where('user_id', $buyer->id)->firstOrFail();
        $attempt = PaymentAttempt::where('order_id', $order->id)->firstOrFail();

        $this->assertSame('pending', $order->status);
        $this->assertSame('pending', $attempt->status);

        $this->get(route('payment.attempt.show', ['token' => $attempt->token]))->assertOk();

        $order->refresh();
        $attempt->refresh();
        $this->assertSame('pending', $order->status);
        $this->assertSame('pending', $attempt->status);
    }



    public function test_payment_expires_after_five_minutes(): void
    {
        $buyer = $this->buyer();
        Date::setTestNow(Carbon::parse('2026-10-28 10:00:00'));

        $this->checkout($buyer, 'upi')->assertStatus(302);

        $attempt = PaymentAttempt::where('payment_method', 'upi')->where('status', 'pending')->firstOrFail();

        Date::setTestNow(Carbon::parse('2026-10-28 10:05:01'));

        $this->get(route('payment.attempt.show', ['token' => $attempt->token]))
            ->assertOk()
            ->assertSee('Payment timed out', false);

        $attempt->refresh();
        $this->assertSame('expired', $attempt->status);

        Date::setTestNow();
    }

    public function test_expired_payment_cannot_confirm_an_order(): void
    {
        $buyer = $this->buyer();
        Date::setTestNow(Carbon::parse('2026-10-28 10:00:00'));

        $this->checkout($buyer, 'upi')->assertStatus(302);

        $order = Order::where('user_id', $buyer->id)->firstOrFail();
        $attempt = PaymentAttempt::where('order_id', $order->id)->firstOrFail();

        Date::setTestNow(Carbon::parse('2026-10-28 10:05:01'));

        $this->assertTrue(now()->greaterThan($attempt->expires_at), 'clock not past deadline: ' . now() . ' vs ' . $attempt->expires_at);

        $this->post(route('payment.attempt.pay', ['token' => $attempt->token]))
            ->assertRedirect(route('payment.attempt.show', ['token' => $attempt->token]));

        $attempt->refresh();
        $this->assertSame('expired', $attempt->status);

        $order->refresh();
        $this->assertSame('pending', $order->status);
        $this->assertFalse($order->isConfirmed());

        Date::setTestNow();
    }

    public function test_retry_creates_a_new_payment_attempt(): void
    {
        $buyer = $this->buyer();

        $this->checkout($buyer, 'upi')->assertStatus(302);

        $order = Order::where('user_id', $buyer->id)->firstOrFail();
        $oldAttempt = PaymentAttempt::where('order_id', $order->id)->firstOrFail();

        $this->post(route('payment.attempt.fail', ['token' => $oldAttempt->token]))
            ->assertRedirect(route('payment.attempt.show', ['token' => $oldAttempt->token]));

        $this->post(route('payment.attempt.retry', ['token' => $oldAttempt->token]))
            ->assertRedirect();

        $oldAttempt->refresh();
        $this->assertSame('failed', $oldAttempt->status);

        $attempts = PaymentAttempt::where('order_id', $order->id)->orderBy('id')->get();
        $this->assertCount(2, $attempts, 'Retry must create a new attempt, not reuse the expired/failed one.');
        $newAttempt = $attempts->last();
        $this->assertNotSame($oldAttempt->token, $newAttempt->token);
        $this->assertSame('pending', $newAttempt->status);
        $this->assertTrue($newAttempt->expires_at->greaterThanOrEqualTo($oldAttempt->expires_at));
    }

    public function test_refreshing_the_page_does_not_reset_the_expiry(): void
    {
        $buyer = $this->buyer();
        Date::setTestNow(Carbon::parse('2026-10-28 10:00:00'));

        $this->checkout($buyer, 'upi')->assertStatus(302);

        $attempt = PaymentAttempt::where('payment_method', 'upi')->where('status', 'pending')->firstOrFail();
        $originalDeadline = $attempt->expires_at->toISOString();

        Date::setTestNow(Carbon::parse('2026-10-28 10:02:00'));

        $this->get(route('payment.attempt.show', ['token' => $attempt->token]))->assertOk();

        $attempt->refresh();
        $this->assertSame($originalDeadline, $attempt->expires_at->toISOString(), 'Refreshing the page must not reset the expiry deadline.');

        Date::setTestNow();
    }


    public function test_duplicate_success_callbacks_do_not_create_duplicate_orders(): void
    {
        $buyer = $this->buyer();

        $this->checkout($buyer, 'card')->assertStatus(302);

        $order = Order::where('user_id', $buyer->id)->firstOrFail();
        $attempt = PaymentAttempt::where('order_id', $order->id)->firstOrFail();

        $this->post(route('payment.attempt.pay', ['token' => $attempt->token]))
            ->assertRedirect(route('checkout.complete'));

        $this->post(route('payment.attempt.pay', ['token' => $attempt->token]))
            ->assertRedirect(route('checkout.complete'));

        $attempt->refresh();
        $this->assertSame('success', $attempt->status);

        $order->refresh();
        $this->assertTrue($order->isConfirmed());

        $this->assertSame(1, OrderStatusHistory::where('order_id', $order->id)->count(), 'Only one confirmation transition may be recorded.');
        $this->assertSame(1, Order::where('user_id', $buyer->id)->count(), 'Duplicate pay must not create a duplicate order.');
    }

    public function test_one_user_cannot_access_another_users_payment_attempt(): void
    {
        $buyerA = $this->buyer();
        $buyerB = $this->buyer();

        $this->checkout($buyerA, 'upi')->assertStatus(302);

        $attempt = PaymentAttempt::where('payment_method', 'upi')->firstOrFail();

        $this->actingAs($buyerB)
            ->get(route('payment.attempt.show', ['token' => $attempt->token]))
            ->assertStatus(403);

        $this->actingAs($buyerB)
            ->post(route('payment.attempt.pay', ['token' => $attempt->token]))
            ->assertStatus(403);

        $this->actingAs($buyerB)
            ->post(route('payment.attempt.fail', ['token' => $attempt->token]))
            ->assertStatus(403);

        $this->actingAs($buyerB)
            ->post(route('payment.attempt.retry', ['token' => $attempt->token]))
            ->assertStatus(403);
    }

    public function test_cod_checkout_continues_to_work(): void
    {
        $buyer = $this->buyer();

        $this->checkout($buyer, 'cod', ['payment_method' => 'cod', 'payment_confirmation' => null])
            ->assertRedirect(route('checkout.complete'));

        $order = Order::where('user_id', $buyer->id)->firstOrFail();
        $this->assertSame('Cash on Delivery', $order->payment_method);
        $this->assertNull($order->payment_provider);
        $this->assertSame(0, PaymentAttempt::where('order_id', $order->id)->count(), 'COD must not create a payment attempt.');
    }

    public function test_inventory_is_not_deducted_multiple_times(): void
    {
        $buyer = $this->buyer();

        $product = Product::where('slug', 'smart-watch-pro')->firstOrFail();
        $before = (int) $product->quantity;
        $this->assertGreaterThan(0, $before, 'Seed product must have stock for this test.');

        $this->checkout($buyer, 'card')->assertStatus(302);

        $order = Order::where('user_id', $buyer->id)->firstOrFail();
        $attempt = PaymentAttempt::where('order_id', $order->id)->firstOrFail();

        $this->assertSame($before - 1, (int) $product->fresh()->quantity);

        $this->post(route('payment.attempt.pay', ['token' => $attempt->token]))
            ->assertRedirect(route('checkout.complete'));

        $this->assertSame($before - 1, (int) $product->fresh()->quantity);

        $this->post(route('payment.attempt.pay', ['token' => $attempt->token]))
            ->assertRedirect(route('checkout.complete'));

        $this->assertSame($before - 1, (int) $product->fresh()->quantity);

        $this->assertSame(
            $order->items->count(),
            InventoryTransaction::where('type', 'sale')->whereIn('order_item_id', $order->items->pluck('id'))->count(),
            'Inventory must be deducted exactly once per order line.'
        );
    }
}








