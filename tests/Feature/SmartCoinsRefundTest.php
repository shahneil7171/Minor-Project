<?php

namespace Tests\Feature;

use App\Models\CodRefundPayout;
use App\Models\Order;
use App\Models\OrderDelivery;
use App\Models\OrderItem;
use App\Models\Refund;
use App\Models\ReturnRequest;
use App\Models\SmartCoinTransaction;
use App\Models\SmartWallet;
use App\Models\User;
use App\Services\RefundService;
use App\Services\SmartCoinsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Smart Coins wallet, refund settlement, replacement and COD payout tests.
 *
 * Focuses on the money-safety guarantees: server-side refund/coin amounts,
 * idempotent credits/debits, no double refunds, no negative balances, COD
 * payouts that require confirmation, and strict role isolation.
 */
class SmartCoinsRefundTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['account_type' => 'admin']);
    }

    private function buyer(): User
    {
        return User::factory()->create(['account_type' => 'buyer']);
    }

    private function seller(): User
    {
        return User::factory()->create(['account_type' => 'seller']);
    }

    private function partner(): User
    {
        return User::factory()->create(['account_type' => 'delivery_partner', 'status' => 'active']);
    }

    private function makeOrder(User $user, string $status = 'delivered', array $overrides = []): Order
    {
        return Order::create(array_merge([
            'user_id' => $user->id,
            'customer_email' => $user->email,
            'order_number' => 'KDP-' . strtoupper(uniqid()),
            'status' => $status,
            'subtotal' => 500.00,
            'discount_amount' => 0,
            'coupon_code' => null,
            'tax' => 0,
            'shipping_cost' => 0,
            'total' => 500.00,
            'coins_redeemed' => 0,
            'coins_refunded' => 0,
            'payment_method' => 'cod',
            'shipping_name' => $user->name,
            'shipping_phone' => '9999999999',
            'shipping_address' => '123 Main St',
            'shipping_city' => 'Springfield',
            'shipping_state' => 'IL',
            'shipping_pincode' => '10001',
        ], $overrides));
    }

    private function addItem(Order $order, ?User $seller = null, float $price = 500.00, int $quantity = 3, string $slug = 'test-product'): OrderItem
    {
        return $order->items()->create([
            'order_id' => $order->id,
            'product_slug' => $slug,
            'product_title' => 'Test Product',
            'product_image' => null,
            'sku' => 'TP-001',
            'price' => $price,
            'quantity' => $quantity,
            'subtotal' => $price * $quantity,
            'options_text' => null,
            'seller_id' => $seller?->id,
        ]);
    }

    private function deliverOrder(Order $order, ?Carbon $deliveredAt = null): OrderDelivery
    {
        $delivery = OrderDelivery::create([
            'order_id' => $order->id,
            'delivery_partner_id' => $this->partner()->id,
            'assigned_by' => $this->admin()->id,
            'status' => 'delivered',
            'assigned_at' => $deliveredAt ?? now(),
            'delivered_at' => $deliveredAt ?? now(),
        ]);
        $order->update(['status' => 'delivered']);

        return $delivery;
    }

    private function receivedReturn(OrderItem $item, User $buyer, int $qty = 1): ReturnRequest
    {
        return ReturnRequest::create([
            'order_id' => $item->order_id,
            'order_item_id' => $item->id,
            'user_id' => $buyer->id,
            'order_number' => $item->order->order_number,
            'customer_email' => $buyer->email,
            'product_slug' => $item->product_slug,
            'seller_id' => $item->seller_id,
            'product_title' => $item->product_title,
            'quantity' => $qty,
            'reason' => 'Product damaged',
            'status' => 'received',
            'refund_status' => 'none',
            'refund_amount' => $item->price * $qty,
            'return_deadline' => $item->order->returnDeadline(),
            'requested_at' => now(),
        ]);
    }

    private function cartWithWatch(User $buyer): void
    {
        // Authenticate BEFORE saving so the cart is stored on the user's
        // persistent (DB) cart, not a throwaway session cart.
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
    }

    private function checkoutPost(User $buyer, array $extra = [])
    {
        return $this->actingAs($buyer)->post('/checkout', array_merge([
            'address_option' => 'new',
            'shipping_method' => 'standard',
            'payment_method' => 'cod',
            'coins_to_redeem' => 0,
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
        ], $extra));
    }

    // ------------------------------------------------------------------
    // WALLET + REFUND SETTLEMENT
    // ------------------------------------------------------------------

    public function test_wallet_page_shows_zero_balance_for_new_customer(): void
    {
        $buyer = $this->buyer();

        $this->actingAs($buyer)
            ->get(route('wallet.index'))
            ->assertOk()
            ->assertSee('Smart Coins')
            ->assertSee('0.00 coins');
    }

    public function test_partial_return_refund_credits_exact_amount_only_once(): void
    {
        $buyer = $this->buyer();
        $order = $this->makeOrder($buyer);
        // 3 units at 500 => total 1500. Return 1 unit => refund 500.
        $item = $this->addItem($order, $this->seller(), 500.00, 3);
        $this->deliverOrder($order);

        $return = $this->receivedReturn($item, $buyer, 1);
        $admin = $this->admin();

        $refunds = app(RefundService::class);
        $refund = $refunds->initiate($return, $admin, 'smart_coins');

        $this->assertSame(500.0, (float) $refund->amount, 'One unit of a 3x500 line refunds 500, not 1500.');

        $refunds->processSmartCoins($refund->fresh(), $admin);

        $wallet = SmartWallet::where('user_id', $buyer->id)->first();
        $this->assertSame(500.0, (float) $wallet->balance);
        $this->assertSame(1, SmartCoinTransaction::where('user_id', $buyer->id)->where('type', 'refund_credit')->count());

        // Re-running the SAME refund must NOT credit coins a second time.
        $refunds->processSmartCoins($refund->fresh(), $admin);
        $wallet->refresh();
        $this->assertSame(500.0, (float) $wallet->balance, 'A retried refund must not double-credit.');
        $this->assertSame(1, SmartCoinTransaction::where('user_id', $buyer->id)->where('type', 'refund_credit')->count());
    }

    public function test_smart_coins_refund_marks_refund_completed_only_after_credit(): void
    {
        $buyer = $this->buyer();
        $order = $this->makeOrder($buyer);
        $item = $this->addItem($order, $this->seller(), 200.00, 1);
        $this->deliverOrder($order);

        $return = $this->receivedReturn($item, $buyer, 1);
        $admin = $this->admin();

        $refunds = app(RefundService::class);
        $refund = $refunds->initiate($return, $admin, 'smart_coins');

        // Before crediting, the refund is pending and the wallet is empty.
        $this->assertSame('pending', $refund->status);
        $this->assertSame(0.0, app(SmartCoinsService::class)->balance($buyer));

        $refunds->processSmartCoins($refund->fresh(), $admin);

        $this->assertSame('completed', $refund->fresh()->status);
        $this->assertSame(200.0, app(SmartCoinsService::class)->balance($buyer));
        $this->assertSame('refunded', $return->fresh()->refund_status);
    }

    public function test_duplicate_refund_is_prevented_for_same_return(): void
    {
        $buyer = $this->buyer();
        $order = $this->makeOrder($buyer);
        $item = $this->addItem($order, $this->seller(), 300.00, 1);
        $this->deliverOrder($order);

        $return = $this->receivedReturn($item, $buyer, 1);
        $admin = $this->admin();
        $refunds = app(RefundService::class);

        $first = $refunds->initiate($return, $admin, 'smart_coins');
        $second = $refunds->initiate($return, $admin, 'smart_coins');

        $this->assertSame($first->id, $second->id, 'Initiate is idempotent: one refund per return.');
        $this->assertSame(1, Refund::where('return_request_id', $return->id)->count());
    }

    public function test_simulated_online_refund_is_not_marked_as_a_real_transfer(): void
    {
        $buyer = $this->buyer();
        $order = $this->makeOrder($buyer, 'delivered', ['payment_method' => 'UPI']);
        $item = $this->addItem($order, $this->seller(), 400.00, 1);
        $this->deliverOrder($order);

        $return = $this->receivedReturn($item, $buyer, 1);
        $admin = $this->admin();
        $refunds = app(RefundService::class);

        $refund = $refunds->initiate($return, $admin, 'original_simulated');
        $this->assertSame('simulated', $refund->provider);

        $refunds->processOnlineRefund($refund->fresh(), $admin);

        $done = $refund->fresh();
        $this->assertSame('completed', $done->status);
        $this->assertStringStartsWith('SIMULATED-', (string) $done->provider_reference);
        $this->assertStringContainsString('no real bank transfer', strtolower((string) $done->note));
    }

    public function test_coins_cannot_be_spent_below_zero(): void
    {
        $buyer = $this->buyer();
        app(SmartCoinsService::class)->credit($buyer, 100, 'refund_credit', 'refund', 1, 'seed-100', 'seed');

        $coins = app(SmartCoinsService::class);
        $coins->debit($buyer, 100, 'checkout_redemption', 'order', 1, 'spend-1', 'spend');
        $this->assertSame(0.0, $coins->balance($buyer));

        // A second debit of any amount must fail (never negative).
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $coins->debit($buyer, 1, 'checkout_redemption', 'order', 2, 'spend-2', 'spend again');
    }

    public function test_wallet_has_no_customer_write_route(): void
    {
        $this->assertNull(app('router')->getRoutes()->getByName('wallet.adjust'));

        $buyer = $this->buyer();
        $this->actingAs($buyer)->get(route('wallet.index'))->assertOk();
    }


    // ------------------------------------------------------------------
    // COD CASH REFUND PAYOUT (must require confirmation)
    // ------------------------------------------------------------------

    public function test_cod_cash_refund_requires_confirmation_before_completion(): void
    {
        $buyer = $this->buyer();
        $order = $this->makeOrder($buyer, 'delivered', ['payment_method' => 'Cash on Delivery']);
        $item = $this->addItem($order, $this->seller(), 250.00, 1);
        $this->deliverOrder($order);

        $return = $this->receivedReturn($item, $buyer, 1);
        $admin = $this->admin();
        $refunds = app(RefundService::class);

        $refund = $refunds->initiate($return, $admin, 'cod_cash');
        $this->assertSame('pending', $refund->status);
        $this->assertSame(1, CodRefundPayout::where('refund_id', $refund->id)->count());

        $partner = $this->partner();
        $payout = CodRefundPayout::where('refund_id', $refund->id)->first();
        $payout->update(['delivery_partner_id' => $partner->id]);

        // Confirm WITHOUT the required confirmation value => rejected.
        $this->actingAs($partner)
            ->post(route('delivery.payouts.confirm', ['payout' => $payout->id]), [])
            ->assertSessionHasErrors('confirmation');
        $this->assertSame('pending', $refund->fresh()->status);

        // Confirm WITH the required confirmation value => completed.
        $this->actingAs($partner)
            ->post(route('delivery.payouts.confirm', ['payout' => $payout->id]), ['confirmation' => 'OTP-482913'])
            ->assertRedirect();

        $this->assertSame('completed', $refund->fresh()->status);
        $this->assertSame('confirmed', $payout->fresh()->status);
        $this->assertSame($partner->id, (int) $payout->fresh()->confirmed_by);
    }

    public function test_unrelated_delivery_partner_cannot_confirm_a_payout(): void
    {
        $buyer = $this->buyer();
        $order = $this->makeOrder($buyer, 'delivered', ['payment_method' => 'Cash on Delivery']);
        $item = $this->addItem($order, $this->seller(), 250.00, 1);
        $this->deliverOrder($order);

        $return = $this->receivedReturn($item, $buyer, 1);
        $admin = $this->admin();
        $refund = app(RefundService::class)->initiate($return, $admin, 'cod_cash');

        $assigned = $this->partner();
        $payout = CodRefundPayout::where('refund_id', $refund->id)->first();
        $payout->update(['delivery_partner_id' => $assigned->id]);

        $other = User::factory()->create(['account_type' => 'delivery_partner', 'status' => 'active']);
        $this->actingAs($other)
            ->post(route('delivery.payouts.confirm', ['payout' => $payout->id]), ['confirmation' => 'OTP-000000'])
            ->assertForbidden();

        $this->assertSame('pending', $refund->fresh()->status);
    }

    public function test_failed_cod_payout_can_be_retried_without_duplicate_refund(): void
    {
        $buyer = $this->buyer();
        $order = $this->makeOrder($buyer, 'delivered', ['payment_method' => 'Cash on Delivery']);
        $item = $this->addItem($order, $this->seller(), 250.00, 1);
        $this->deliverOrder($order);

        $return = $this->receivedReturn($item, $buyer, 1);
        $admin = $this->admin();
        $refunds = app(RefundService::class);
        $refund = $refunds->initiate($return, $admin, 'cod_cash');

        $refunds->markFailed($refund->fresh(), $admin, 'Customer not home');
        $this->assertSame('failed', $refund->fresh()->status);

        // Retry re-opens the SAME refund record — never a second one.
        $refunds->retry($refund->fresh(), $admin);
        $this->assertSame(1, Refund::where('return_request_id', $return->id)->count());
    }

    // ------------------------------------------------------------------
    // REPLACEMENT
    // ------------------------------------------------------------------

    public function test_replacement_is_linked_to_return_and_not_duplicated(): void
    {
        $buyer = $this->buyer();
        $order = $this->makeOrder($buyer);
        $item = $this->addItem($order, $this->seller(), 500.00, 1);
        $this->deliverOrder($order);

        $return = $this->receivedReturn($item, $buyer, 1);
        $service = app(\App\Services\ReplacementService::class);

        $replacement = $service->create($return, ['product_slug' => $item->product_slug, 'variant_id' => $item->variant_id, 'quantity' => 1]);
        $again = $service->create($return, []);

        $this->assertSame($replacement->id, $again->id, 'One replacement per return.');
        $this->assertSame($return->id, (int) $replacement->return_request_id);
        // Same product + same variant => no price difference, no charge.
        $this->assertSame(0.0, (float) $replacement->price_difference);
        $this->assertSame('not_required', $replacement->payment_status);
    }


    // ------------------------------------------------------------------
    // CHECKOUT COIN REDEMPTION
    // ------------------------------------------------------------------

    public function test_coins_redeemed_at_checkout_reduce_the_amount_due(): void
    {
        $buyer = $this->buyer();
        app(SmartCoinsService::class)->credit($buyer, 500, 'refund_credit', 'refund', 1, 'seed-500', 'seed');
        $this->cartWithWatch($buyer);

        // Payable includes 18% tax + 50 shipping. Redeem 200 coins => less due.
        $this->checkoutPost($buyer, ['coins_to_redeem' => 200])->assertRedirect('/checkout/complete');

        $order = Order::where('user_id', $buyer->id)->firstOrFail();
        $this->assertSame(200.0, (float) $order->coins_redeemed);
        // Wallet: 500 seeded - 200 redeemed = 300.
        $this->assertSame(300.0, app(SmartCoinsService::class)->balance($buyer));
    }

    public function test_checkout_does_not_trust_a_tampered_coin_amount(): void
    {
        $buyer = $this->buyer();
        app(SmartCoinsService::class)->credit($buyer, 50, 'refund_credit', 'refund', 1, 'seed-50', 'seed');
        $this->cartWithWatch($buyer);

        // Buyer claims 999999 coins — the server must cap it at the real balance.
        $this->checkoutPost($buyer, ['coins_to_redeem' => 999999])->assertRedirect('/checkout/complete');

        $order = Order::where('user_id', $buyer->id)->firstOrFail();
        $this->assertSame(50.0, (float) $order->coins_redeemed, 'Redemption capped to the real 50-coin balance.');
        $this->assertSame(0.0, app(SmartCoinsService::class)->balance($buyer));
    }

    public function test_full_coin_checkout_creates_a_valid_order(): void
    {
        $buyer = $this->buyer();
        // Enough coins to cover 199 + tax + 50 shipping.
        app(SmartCoinsService::class)->credit($buyer, 500, 'refund_credit', 'refund', 1, 'seed-500', 'seed');
        $this->cartWithWatch($buyer);

        $this->checkoutPost($buyer, ['coins_to_redeem' => 500])->assertRedirect('/checkout/complete');

        $order = Order::where('user_id', $buyer->id)->firstOrFail();
        // Coins are capped at the payable total (never more than the order).
        $this->assertGreaterThan(0.0, (float) $order->coins_redeemed);
        $this->assertLessThanOrEqual(500.0, (float) $order->coins_redeemed);
        $this->assertSame('pending', $order->status);
    }

    public function test_cancelled_order_restores_redeemed_coins_once(): void
    {
        $buyer = $this->buyer();
        app(SmartCoinsService::class)->credit($buyer, 500, 'refund_credit', 'refund', 1, 'seed-500', 'seed');

        $order = $this->makeOrder($buyer, 'confirmed', ['coins_redeemed' => 300]);

        // Simulate the redemption debit that checkout would have made.
        app(SmartCoinsService::class)->debit($buyer, 300, 'checkout_redemption', 'order', $order->id, 'redeem-' . $order->id, 'seed redeem');
        $this->assertSame(200.0, app(SmartCoinsService::class)->balance($buyer));

        // Cancel the order through the real status service.
        app(\App\Services\OrderStatusService::class)->transition($order, 'cancelled', $buyer, 'changed mind');

        // Coins are restored exactly once.
        $this->assertSame(500.0, app(SmartCoinsService::class)->balance($buyer));
        $this->assertSame(1, SmartCoinTransaction::where('type', 'order_cancellation_restoration')
            ->where('reference_id', $order->id)->count());
    }

    // ------------------------------------------------------------------
    // AUTHORIZATION
    // ------------------------------------------------------------------

    public function test_non_admin_cannot_initiate_a_refund(): void
    {
        $buyer = $this->buyer();
        $order = $this->makeOrder($buyer);
        $item = $this->addItem($order, $this->seller(), 300.00, 1);
        $this->deliverOrder($order);
        $return = $this->receivedReturn($item, $buyer, 1);

        $this->actingAs($buyer)
            ->post(route('admin.returns.refund-initiate', ['return' => $return->id]), ['method' => 'smart_coins'])
            ->assertForbidden();

        $this->assertSame(0, Refund::where('return_request_id', $return->id)->count());
    }

    public function test_admin_refund_amount_is_computed_not_submitted(): void
    {
        $buyer = $this->buyer();
        $order = $this->makeOrder($buyer);
        // 3 units at 500 => return 1 => refund 500.
        $item = $this->addItem($order, $this->seller(), 500.00, 3);
        $this->deliverOrder($order);
        $return = $this->receivedReturn($item, $buyer, 1);

        $admin = $this->admin();
        $refund = app(RefundService::class)->initiate($return, $admin, 'smart_coins');

        // The server computed 500 for a single unit — never the whole 1500 line.
        $this->assertSame(500.0, (float) $refund->amount);
    }

    // ------------------------------------------------------------------
    // VIEW RENDERING (smoke tests for the new admin / delivery pages)
    // ------------------------------------------------------------------

    public function test_admin_return_show_renders_refund_method_card(): void
    {
        $buyer = $this->buyer();
        $order = $this->makeOrder($buyer);
        $item = $this->addItem($order, $this->seller(), 300.00, 1);
        $this->deliverOrder($order);
        $return = $this->receivedReturn($item, $buyer, 1);

        $this->actingAs($this->admin())
            ->get(route('admin.returns.show', ['return' => $return->id]))
            ->assertOk()
            ->assertSee('Refund method');
    }

    public function test_delivery_payout_pages_render(): void
    {
        $buyer = $this->buyer();
        $order = $this->makeOrder($buyer, 'delivered', ['payment_method' => 'Cash on Delivery']);
        $item = $this->addItem($order, $this->seller(), 250.00, 1);
        $this->deliverOrder($order);
        $return = $this->receivedReturn($item, $buyer, 1);

        $admin = $this->admin();
        $refund = app(RefundService::class)->initiate($return, $admin, 'cod_cash');
        $partner = $this->partner();
        $payout = CodRefundPayout::where('refund_id', $refund->id)->first();
        $payout->update(['delivery_partner_id' => $partner->id]);

        $this->actingAs($partner)->get(route('delivery.payouts.index'))->assertOk();
        $this->actingAs($partner)
            ->get(route('delivery.payouts.show', ['payout' => $payout->id]))
            ->assertOk()
            ->assertSee('Confirm payout');
    }
}
