<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Mail\OrderConfirmationMail;
use App\Models\PaymentAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * SIMULATED CARD PAYMENT (demo only).
 *
 * The checkout card panel accepts only the public test card 4111 1111 1111
 * 1111 with CVV 123 and a future expiry. These tests prove that:
 *  - a valid demo card completes checkout and stores ONLY the method label,
 *  - real-looking (but non-demo) card numbers are rejected,
 *  - missing/expired card details are rejected,
 *  - no card number / CVV / expiry value ever reaches the orders table.
 */
class CheckoutCardPaymentTest extends TestCase
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

    private function checkout(User $user, array $post = [])
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

        return $this->post('/checkout', array_merge([
            'address_option' => 'new',
            'shipping_method' => 'standard',
            'payment_method' => 'card',
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
        ], $post));
    }

    private function validCard(): array
    {
        return [
            'card_name' => 'Jane Doe',
            'card_number' => '4111111111111111',
            'card_expiry' => '12/30',
            'card_cvv' => '123',
        ];
    }

    /** Follow a card checkout through the payment-attempt flow to success. */
    private function completeCardCheckout(User $user, array $card): Order
    {
        $response = $this->checkout($user, $card);

        // Card checkouts no longer complete immediately: they create a
        // payment attempt with a five-minute deadline and redirect to it.
        $response->assertStatus(302);

        $order = Order::where('user_id', $user->id)->firstOrFail();
        $attempt = PaymentAttempt::where('order_id', $order->id)->firstOrFail();
        $this->assertSame('pending', $attempt->status);
        $this->assertSame((float) $order->total, (float) $attempt->amount);

        $this->get(route('payment.attempt.show', ['token' => $attempt->token]))
            ->assertOk()
            ->assertSee('id="countdown"', false)
            ->assertSee('data-deadline', false);

        Mail::fake();

        $this->post(route('payment.attempt.pay', ['token' => $attempt->token]))
            ->assertRedirect(route('checkout.complete'));

        $order->refresh();
        $this->assertTrue($order->isConfirmed(), 'A verified simulated card payment must confirm the order.');
        $attempt->refresh();
        $this->assertSame('success', $attempt->status);
        $this->assertNotNull($attempt->provider_reference);
        $this->assertNotNull($attempt->verified_at);

        Mail::assertSent(OrderConfirmationMail::class);

        return $order;
    }

    public function test_valid_demo_card_completes_checkout_and_stores_only_the_method(): void
    {
        $buyer = $this->buyer();

        $order = $this->completeCardCheckout($buyer, $this->validCard());

        // The stored method is the human label â€” never the raw card data.
        $this->assertSame('Card', $order->payment_method);

        // No card field may appear anywhere in the persisted order row.
        $raw = json_encode($order->getAttributes());
        $this->assertStringNotContainsString('4111111111111111', $raw);
        $this->assertStringNotContainsString('"card_cvv"', $raw);
        $this->assertStringNotContainsString('"card_number"', $raw);
        $this->assertStringNotContainsString('"card_expiry"', $raw);
    }

    public function test_card_number_with_spaces_is_accepted(): void
    {
        $buyer = $this->buyer();
        $card = $this->validCard();
        $card['card_number'] = '4111 1111 1111 1111';

        $order = $this->completeCardCheckout($buyer, $card);

        $this->assertSame('Card', $order->payment_method);
    }

    public function test_non_demo_card_number_is_rejected(): void
    {
        $buyer = $this->buyer();
        $card = $this->validCard();
        $card['card_number'] = '5555555555554444'; // Mastercard test number â€” not the demo card.

        $this->checkout($buyer, $card)
            ->assertSessionHasErrors('card_number');

        $this->assertSame(0, Order::where('user_id', $buyer->id)->count());
    }

    public function test_missing_card_fields_are_rejected(): void
    {
        $buyer = $this->buyer();

        $this->checkout($buyer, [
            'card_name' => '', 'card_number' => '', 'card_expiry' => '', 'card_cvv' => '',
        ])
            ->assertSessionHasErrors(['card_name', 'card_number', 'card_expiry', 'card_cvv']);

        $this->assertSame(0, Order::where('user_id', $buyer->id)->count());
    }

    public function test_past_card_expiry_is_rejected(): void
    {
        $buyer = $this->buyer();
        $card = $this->validCard();
        $card['card_expiry'] = '01/20'; // Long expired.

        $this->checkout($buyer, $card)
            ->assertSessionHasErrors('card_expiry');

        $this->assertSame(0, Order::where('user_id', $buyer->id)->count());
    }

    public function test_invalid_cvv_is_rejected(): void
    {
        $buyer = $this->buyer();
        $card = $this->validCard();
        $card['card_cvv'] = '12'; // Must be exactly 3 digits.

        $this->checkout($buyer, $card)
            ->assertSessionHasErrors('card_cvv');

        $this->assertSame(0, Order::where('user_id', $buyer->id)->count());
    }

    public function test_cod_checkout_is_not_blocked_by_card_validation(): void
    {
        // Disabled card inputs are not submitted; COD must not require them.
        $buyer = $this->buyer();

        $this->checkout($buyer, [
            'payment_method' => 'cod',
            'payment_confirmation' => null,
            'card_name' => null, 'card_number' => null, 'card_expiry' => null, 'card_cvv' => null,
        ])->assertRedirect('/checkout/complete');

        $this->assertSame('Cash on Delivery', Order::where('user_id', $buyer->id)->firstOrFail()->payment_method);
    }

    public function test_checkout_page_renders_simulated_card_panel(): void
    {
        $buyer = $this->buyer();
        $this->actingAs($buyer);

        app(\App\Services\CartService::class)->save([
            'smart-watch-pro' => [
                'product' => 'smart-watch-pro',
                'title' => 'Smart Watch Pro',
                'price' => 199.0,
                'quantity' => 1,
                'sku' => 'KDP-SMW-001',
                'image' => 'x.jpg',
            ],
        ]);

        $this->get('/checkout')
            ->assertOk()
            ->assertSee('id="cardPaymentBox"', false)
            ->assertSee('name="card_number"', false)
            ->assertSee('name="card_cvv"', false)
            ->assertSee('4111 1111 1111 1111', false);
    }
}

