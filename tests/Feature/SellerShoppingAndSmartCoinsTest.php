<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderDelivery;
use App\Models\Product;
use App\Models\SmartCoinTransaction;
use App\Models\User;
use App\Services\CartService;
use App\Services\OrderStatusService;
use App\Services\SmartCoinsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * PART 1 — sellers shop as customers; PART 2..4 — Smart Coins end-to-end.
 *
 * Financial facts for the seeded "smart-watch-pro" fixture used throughout:
 *   subtotal 199.00 + tax 35.82 (18%) + shipping 50.00 = gross 284.82.
 * 1 Smart Coin = Re 1 (config smart_coins.coin_to_inr).
 */
class SellerShoppingAndSmartCoinsTest extends TestCase
{
    use RefreshDatabase;

    private const WATCH_GROSS = 284.82;

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

    /** Seed the persistent (DB) cart with the watch fixture. */
    private function cartWithWatch(User $user): void
    {
        // Authenticate BEFORE saving so the cart is stored on the user's
        // persistent cart, not a throwaway session cart.
        $this->actingAs($user);

        app(CartService::class)->save([
            'smart-watch-pro' => [
                'product'  => 'smart-watch-pro',
                'title'    => 'Smart Watch Pro',
                'price'    => 199.0,
                'quantity' => 1,
                'sku'      => 'KDP-SMW-001',
                'image'    => 'x.jpg',
            ],
        ]);
    }

    private function checkoutPost(User $user, array $extra = [])
    {
        return $this->actingAs($user)->post('/checkout', array_merge([
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

    /**
     * A pre-fix (legacy) order row created directly: the OLD shape where
     * `total` still holds the pre-coin gross even though coins_redeemed > 0.
     */
    private function makeLegacyOrder(User $user, array $overrides = []): Order
    {
        return Order::create(array_merge([
            'user_id' => $user->id,
            'customer_email' => $user->email,
            'order_number' => 'KDP-' . strtoupper(uniqid()),
            'status' => 'delivered',
            'subtotal' => 189.00,
            'discount_amount' => 0,
            'coupon_code' => null,
            'tax' => 34.02,
            'shipping_cost' => 50.00,
            'total' => 273.02,
            'coins_redeemed' => 0,
            'coins_refunded' => 0,
            'payment_method' => 'Cash on Delivery',
            'shipping_name' => $user->name,
            'shipping_phone' => '9999999999',
            'shipping_address' => '123 Main St',
            'shipping_city' => 'Springfield',
            'shipping_state' => 'IL',
            'shipping_pincode' => '10001',
        ], $overrides));
    }

    // ------------------------------------------------------------------
    // PART 1 — SELLERS SHOP AS CUSTOMERS
    // ------------------------------------------------------------------

    /**
     * Requirement 1: a seller can add to cart, open checkout and place an
     * order like any other shopper (no blanket account-type block anywhere).
     */
    public function test_seller_can_purchase_a_product_they_are_permitted_to_buy(): void
    {
        $seller = $this->seller();

        $this->actingAs($seller)->post('/cart/add/smart-watch-pro')
            ->assertRedirect('/cart');

        // The cart and checkout pages carry NO blanket seller block.
        $this->actingAs($seller)->get(route('cart.index'))
            ->assertOk()
            ->assertDontSee('Seller accounts can', false);
        $this->actingAs($seller)->get(route('checkout.index'))
            ->assertOk()
            ->assertDontSee('Seller accounts can', false);

        $this->checkoutPost($seller)->assertRedirect('/checkout/complete');

        $order = Order::where('user_id', $seller->id)->firstOrFail();
        $this->assertSame('pending', $order->status);
        $this->assertSame(self::WATCH_GROSS, (float) $order->total);
        $this->assertSame('Cash on Delivery', $order->payment_method);
        $this->assertSame(1, $order->items()->count());
    }

    /**
     * Requirement 2: sellers may also buy Admin-owned (platform) catalog
     * products and other sellers' products — ownership never gates shopping.
     */
    public function test_seller_can_buy_admin_owned_catalog_products(): void
    {
        $seller = $this->seller();
        // 'smart-watch-pro' is a seed-catalog (admin-owned) product:
        // seller_id null means platform-owned.
        $this->assertNull(
            Product::where('slug', 'smart-watch-pro')->value('seller_id'),
            'Fixture assumption: the watch belongs to the Admin store.'
        );

        $this->cartWithWatch($seller);
        $this->checkoutPost($seller)->assertRedirect('/checkout/complete');

        $this->assertSame(1, Order::where('user_id', $seller->id)->count());
    }

    /**
     * Requirement 5: product MANAGEMENT authorization is untouched —
     * sellers still get the create form, only owners (and admins) get the
     * edit form, and buyers get neither.
     */
    public function test_seller_product_editing_permissions_remain_intact(): void
    {
        $seller = $this->seller();
        $other = $this->seller();
        $buyer = $this->buyer();

        // Sellers may open the create form.
        $this->actingAs($seller)->get('/products/create')->assertOk();

        // Buyers still can't.
        $this->actingAs($buyer)->get('/products/create')
            ->assertRedirect(route('products'))
            ->assertSessionHas('error', 'Only sellers or admins can add products.');

        // The seller owns the product => edit form opens.
        Product::where('slug', 'smart-watch-pro')->update(['seller_id' => $seller->id]);
        $this->actingAs($seller)->get('/products/smart-watch-pro/edit')->assertOk();

        // Another seller still gets a 403 (ownership enforcement intact).
        $this->actingAs($other)->get('/products/smart-watch-pro/edit')->assertForbidden();

        // Buyers are redirected away from the edit form.
        $this->actingAs($buyer)->get('/products/smart-watch-pro/edit')
            ->assertRedirect(route('products'))
            ->assertSessionHas('error', 'Only sellers or admins can edit products.');
    }

    /**
     * Requirements 3+4: the established marketplace rule is that ownership
     * governs MANAGEMENT, never shopping — NO self-purchase prohibition
     * exists anywhere in the codebase (see AccountSeparationTest), so a
     * seller may buy any product, including their own. This test pins that
     * rule down; if a per-product prohibition is ever introduced, this is
     * the behaviour that must be enforced per-item, never by blocking the
     * seller's whole cart.
     */
    public function test_seller_may_buy_their_own_product_under_the_established_marketplace_rule(): void
    {
        $seller = $this->seller();
        Product::where('slug', 'smart-watch-pro')->update(['seller_id' => $seller->id]);

        $this->actingAs($seller)->post('/cart/add/smart-watch-pro')
            ->assertRedirect('/cart')
            ->assertSessionHas('success');

        $this->checkoutPost($seller)->assertRedirect('/checkout/complete');
        $this->assertSame(1, Order::where('user_id', $seller->id)->count());
    }

    // ------------------------------------------------------------------
    // PART 2 + 3 — SMART COINS REDEMPTION, PERSISTED TOTAL, LEDGER
    // ------------------------------------------------------------------

    /** Requirement 4: a buyer with enough coins can redeem them successfully. */
    public function test_buyer_with_enough_coins_can_redeem_them(): void
    {
        $buyer = $this->buyer();
        app(SmartCoinsService::class)->credit($buyer, 350, 'refund_credit', 'refund', 1, 'redeem-setup', 'seeded wallet');
        $this->cartWithWatch($buyer);

        $this->checkoutPost($buyer, ['coins_to_redeem' => 100])
            ->assertRedirect('/checkout/complete');

        $order = Order::where('user_id', $buyer->id)->firstOrFail();
        $this->assertSame(100.0, (float) $order->coins_redeemed);
        $this->assertSame(250.0, app(SmartCoinsService::class)->balance($buyer));
    }

    /**
     * Requirement 5 (THE core bug): redeemed coins must reduce the PERSISTED
     * final order total. gross 284.82 − 100 coins = 184.82 payable.
     */
    public function test_redeemed_coins_reduce_the_persisted_final_order_total(): void
    {
        $buyer = $this->buyer();
        app(SmartCoinsService::class)->credit($buyer, 350, 'refund_credit', 'refund', 1, 'persist-setup', 'seeded wallet');
        $this->cartWithWatch($buyer);

        $this->checkoutPost($buyer, ['coins_to_redeem' => 100])
            ->assertRedirect('/checkout/complete');

        $order = Order::where('user_id', $buyer->id)->firstOrFail();

        // The full authoritative breakdown, all persisted server-side.
        $this->assertSame(199.00, (float) $order->subtotal);
        $this->assertSame(35.82, (float) $order->tax);
        $this->assertSame(50.00, (float) $order->shipping_cost);
        $this->assertSame(0.0, (float) $order->discount_amount);
        $this->assertSame(100.0, (float) $order->coins_redeemed);
        $this->assertSame(self::WATCH_GROSS, $order->originalTotal()); // before coins
        $this->assertSame(100.0, $order->coinsValue());                // 100 × Re 1
        $this->assertSame(184.82, (float) $order->total);              // FINAL payable
        $this->assertSame(184.82, $order->amountDue());

        // Item prices are the ORIGINAL prices (never overwritten by discount).
        $item = $order->items()->first();
        $this->assertSame(199.00, (float) $item->price);
        $this->assertSame(199.00, (float) $item->subtotal);
    }

    /** Requirement 8: the wallet balance decreases exactly once. */
    public function test_wallet_balance_decreases_exactly_once(): void
    {
        $buyer = $this->buyer();
        app(SmartCoinsService::class)->credit($buyer, 350, 'refund_credit', 'refund', 1, 'once-setup', 'seeded wallet');
        $this->cartWithWatch($buyer);

        $this->checkoutPost($buyer, ['coins_to_redeem' => 100])->assertRedirect('/checkout/complete');

        // 350 − 100 = 250 — not 150 (double debit) and not 350 (no debit).
        $this->assertSame(250.0, app(SmartCoinsService::class)->balance($buyer));
        $wallet = \App\Models\SmartWallet::where('user_id', $buyer->id)->firstOrFail();
        $this->assertSame(250.0, (float) $wallet->balance);
    }

    /** Requirement 9: exactly ONE ledger debit, linked to the order. */
    public function test_wallet_ledger_contains_exactly_one_corresponding_debit(): void
    {
        $buyer = $this->buyer();
        app(SmartCoinsService::class)->credit($buyer, 350, 'refund_credit', 'refund', 1, 'ledger-setup', 'seeded wallet');
        $this->cartWithWatch($buyer);

        $this->checkoutPost($buyer, ['coins_to_redeem' => 100])->assertRedirect('/checkout/complete');
        $order = Order::where('user_id', $buyer->id)->firstOrFail();

        $debits = SmartCoinTransaction::where('user_id', $buyer->id)
            ->where('type', 'checkout_redemption')
            ->where('direction', 'debit')
            ->get();

        $this->assertCount(1, $debits, 'Exactly one redemption debit must exist.');
        $this->assertSame(100.0, (float) $debits->first()->amount);
        $this->assertSame($order->id, (int) $debits->first()->reference_id);
        $this->assertSame('order', $debits->first()->reference_type);
        $this->assertStringContainsString('#' . $order->order_number, (string) $debits->first()->description);
        $this->assertStringContainsString('Smart Coins redeemed on order', (string) $debits->first()->description);

        // The wallet page reflects the same, single-deduction balance.
        $this->actingAs($buyer)->get(route('wallet.index'))
            ->assertOk()
            ->assertSee('250.00 coins')
            ->assertSee('Smart Coins redeemed on order #' . $order->order_number);
    }

    // ------------------------------------------------------------------
    // PART 2B + 2C — DISPLAY: CHECKOUT LIMITS AND PERSISTED TOTALS
    // ------------------------------------------------------------------

    /** Requirement 6: the order confirmation shows the discounted total. */
    public function test_order_confirmation_shows_the_discounted_total(): void
    {
        $buyer = $this->buyer();
        app(SmartCoinsService::class)->credit($buyer, 350, 'refund_credit', 'refund', 1, 'confirm-setup', 'seeded wallet');
        $this->cartWithWatch($buyer);
        $this->checkoutPost($buyer, ['coins_to_redeem' => 100])->assertRedirect('/checkout/complete');

        $this->actingAs($buyer)->get(route('checkout.complete'))
            ->assertOk()
            ->assertSee('Original total')                        // gross row
            ->assertSee('Smart Coins discount')                  // coin row
            ->assertSee('184.82')                                // final payable
            ->assertDontSee('<span>Total amount</span>', false);  // old label is gone
    }

    /** Requirement 7: My Orders and order details show the same amount. */
    public function test_my_orders_and_order_details_show_the_same_final_payable_amount(): void
    {
        $buyer = $this->buyer();
        app(SmartCoinsService::class)->credit($buyer, 350, 'refund_credit', 'refund', 1, 'history-setup', 'seeded wallet');
        $this->cartWithWatch($buyer);
        $this->checkoutPost($buyer, ['coins_to_redeem' => 100])->assertRedirect('/checkout/complete');
        $order = Order::where('user_id', $buyer->id)->firstOrFail();

        $this->actingAs($buyer)->get(route('orders.index'))
            ->assertOk()
            ->assertSee('184.82')
            ->assertSee('after 100 Smart Coins');

        $this->actingAs($buyer)->get(route('orders.show', $order))
            ->assertOk()
            ->assertSee('Original total')
            ->assertSee('Smart Coins discount (100 coins)')
            ->assertSee('Amount paid')
            ->assertSee('184.82');
    }

    /** Requirement 10: an excessive request is capped at the real balance. */
    public function test_excessive_redemption_is_capped_to_the_available_balance(): void
    {
        $buyer = $this->buyer();
        app(SmartCoinsService::class)->credit($buyer, 50, 'refund_credit', 'refund', 1, 'cap-setup', 'seeded wallet');
        $this->cartWithWatch($buyer);

        $this->checkoutPost($buyer, ['coins_to_redeem' => 999999])
            ->assertRedirect('/checkout/complete');

        $order = Order::where('user_id', $buyer->id)->firstOrFail();
        $this->assertSame(50.0, (float) $order->coins_redeemed, 'Capped to the real 50-coin balance.');
        $this->assertSame(0.0, app(SmartCoinsService::class)->balance($buyer), 'Balance never negative.');
        $this->assertSame(234.82, (float) $order->total);
    }

    /**
     * Requirement 10 (fractional): 0.47 coins sits below the configured
     * minimum redemption (smart_coins.min_redeem = 1), so the server offers
     * and accepts 0.00 redeemable — displayed as such, balance untouched.
     */
    public function test_fractional_dust_balance_shows_zero_redeemable_and_is_not_spent(): void
    {
        $buyer = $this->buyer();
        app(SmartCoinsService::class)->credit($buyer, 0.47, 'refund_credit', 'refund', 1, 'dust-setup', 'seeded wallet');
        $this->cartWithWatch($buyer);

        $this->actingAs($buyer)->get(route('checkout.index'))
            ->assertOk()
            ->assertSee('Max redeemable on this order')
            ->assertSee('0.00');

        $this->checkoutPost($buyer, ['coins_to_redeem' => 0.47])
            ->assertRedirect('/checkout/complete');

        $order = Order::where('user_id', $buyer->id)->firstOrFail();
        $this->assertSame(0.0, (float) $order->coins_redeemed);
        $this->assertSame(self::WATCH_GROSS, (float) $order->total);
        $this->assertSame(0.47, app(SmartCoinsService::class)->balance($buyer), 'Dust balance untouched.');
    }

    /** Requirement 11: negative and non-numeric values are rejected outright. */
    public function test_negative_or_manipulated_redemption_values_are_rejected(): void
    {
        $buyer = $this->buyer();
        app(SmartCoinsService::class)->credit($buyer, 300, 'refund_credit', 'refund', 1, 'reject-setup', 'seeded wallet');
        $this->cartWithWatch($buyer);

        // Negative: rejected by validation, no order, no wallet movement.
        $this->checkoutPost($buyer, ['coins_to_redeem' => -10])
            ->assertSessionHasErrors('coins_to_redeem');
        $this->assertSame(0, Order::where('user_id', $buyer->id)->count());
        $this->assertSame(300.0, app(SmartCoinsService::class)->balance($buyer));

        // Non-numeric: rejected by validation as well.
        $this->checkoutPost($buyer, ['coins_to_redeem' => 'abc'])
            ->assertSessionHasErrors('coins_to_redeem');
        $this->assertSame(0, Order::where('user_id', $buyer->id)->count());
        $this->assertSame(300.0, app(SmartCoinsService::class)->balance($buyer));
    }

    /** Requirement 12: redemption can never push the total below zero. */
    public function test_redemption_cannot_make_the_order_total_negative(): void
    {
        $buyer = $this->buyer();
        // Balance (500) far exceeds the payable total (284.82).
        app(SmartCoinsService::class)->credit($buyer, 500, 'refund_credit', 'refund', 1, 'neg-setup', 'seeded wallet');
        $this->cartWithWatch($buyer);

        // The checkout advertises the server-computed maximum for THIS order.
        $this->actingAs($buyer)->get(route('checkout.index'))
            ->assertOk()
            ->assertSee('Max redeemable on this order')
            ->assertSee('284.82');

        $this->checkoutPost($buyer, ['coins_to_redeem' => 500])
            ->assertRedirect('/checkout/complete');

        $order = Order::where('user_id', $buyer->id)->firstOrFail();
        $this->assertSame(284.82, (float) $order->coins_redeemed, 'Capped to the order total.');
        $this->assertSame(0.0, (float) $order->total, 'Payable floors at zero, never negative.');
        $this->assertSame(0.0, $order->amountDue());
        $this->assertGreaterThanOrEqual(0.0, (float) $order->total);
        // The unspent remainder stays in the wallet.
        $this->assertSame(215.18, app(SmartCoinsService::class)->balance($buyer));
    }

    // ------------------------------------------------------------------
    // PART 2D — ATOMICITY: FAILED / DUPLICATE / CONCURRENT CHECKOUTS
    // ------------------------------------------------------------------

    /** Requirement 13: a failed checkout never deducts coins. */
    public function test_failed_checkout_does_not_leave_coins_deducted(): void
    {
        $buyer = $this->buyer();
        app(SmartCoinsService::class)->credit($buyer, 300, 'refund_credit', 'refund', 1, 'fail-setup', 'seeded wallet');
        $this->cartWithWatch($buyer);

        // The last unit is gone (another shopper won the race) AFTER the
        // cart was prepared: the checkout must fail cleanly.
        DB::table('products')->where('slug', 'smart-watch-pro')->update(['quantity' => 0]);

        $this->checkoutPost($buyer, ['coins_to_redeem' => 200])
            ->assertRedirect(route('cart.index'))
            ->assertSessionHas('error');

        $this->assertSame(0, Order::where('user_id', $buyer->id)->count(), 'No order may exist.');
        $this->assertSame(300.0, app(SmartCoinsService::class)->balance($buyer), 'Coins untouched.');
        $this->assertSame(0, SmartCoinTransaction::where('user_id', $buyer->id)
            ->where('type', 'checkout_redemption')->count());
    }

    /**
     * Requirement 14: double-clicking Place Order (or retrying after success)
     * never creates a second order or a second coin deduction.
     */
    public function test_duplicate_submissions_do_not_create_duplicate_orders_or_deductions(): void
    {
        $buyer = $this->buyer();
        app(SmartCoinsService::class)->credit($buyer, 350, 'refund_credit', 'refund', 1, 'dup-setup', 'seeded wallet');
        $this->cartWithWatch($buyer);

        $this->checkoutPost($buyer, ['coins_to_redeem' => 100])->assertRedirect('/checkout/complete');

        // The retry finds the cart already emptied by the first submission.
        $this->checkoutPost($buyer, ['coins_to_redeem' => 100])
            ->assertRedirect(route('cart.index'))
            ->assertSessionHas('error', 'Your cart is empty.');

        $this->assertSame(1, Order::where('user_id', $buyer->id)->count(), 'Exactly one order.');
        $this->assertSame(250.0, app(SmartCoinsService::class)->balance($buyer), 'One deduction only.');
        $this->assertSame(1, SmartCoinTransaction::where('user_id', $buyer->id)
            ->where('type', 'checkout_redemption')->count());
    }

    /**
     * Requirement 15: while one checkout is in flight (its shopper lock is
     * held), a concurrent attempt is refused before it can touch the wallet
     * — the same coins can never be spent twice.
     */
    public function test_concurrent_checkout_attempts_cannot_spend_the_same_coins(): void
    {
        $buyer = $this->buyer();
        app(SmartCoinsService::class)->credit($buyer, 350, 'refund_credit', 'refund', 1, 'race-setup', 'seeded wallet');
        $this->cartWithWatch($buyer);

        // Simulate the FIRST checkout still running: its per-shopper lock
        // is held while the second request arrives.
        $lock = Cache::lock('kdp-checkout:user-' . $buyer->id, 15);
        $this->assertTrue($lock->get(), 'Lock simulating the in-flight checkout.');

        $this->checkoutPost($buyer, ['coins_to_redeem' => 100])
            ->assertRedirect(route('cart.index'))
            ->assertSessionHas('error');

        $lock->release();

        $this->assertSame(0, Order::where('user_id', $buyer->id)->count(), 'No order from the refused attempt.');
        $this->assertSame(350.0, app(SmartCoinsService::class)->balance($buyer), 'Wallet untouched.');
        $this->assertSame(0, SmartCoinTransaction::where('user_id', $buyer->id)
            ->where('type', 'checkout_redemption')->count());
    }

    // ------------------------------------------------------------------
    // PART 2E — PAYMENT METHODS: COD AND CANCELLATION
    // ------------------------------------------------------------------

    /**
     * Requirement 16: the delivery partner collects exactly the final
     * payable amount after redemption — never the pre-coin total.
     */
    public function test_cod_collection_amount_matches_the_final_payable_amount(): void
    {
        $buyer = $this->buyer();
        app(SmartCoinsService::class)->credit($buyer, 350, 'refund_credit', 'refund', 1, 'cod-setup', 'seeded wallet');
        $this->cartWithWatch($buyer);
        $this->checkoutPost($buyer, ['coins_to_redeem' => 100])->assertRedirect('/checkout/complete');

        $order = Order::where('user_id', $buyer->id)->firstOrFail();
        $this->assertSame('Cash on Delivery', $order->payment_method);
        // COD cash due == final payable == persisted total.
        $this->assertSame(184.82, $order->amountDue());
        $this->assertSame((float) $order->total, $order->amountDue());

        $partner = $this->partner();
        $delivery = OrderDelivery::create([
            'order_id' => $order->id,
            'delivery_partner_id' => $partner->id,
            'assigned_by' => $buyer->id,
            'status' => 'assigned',
            'assigned_at' => now(),
        ]);

        $this->actingAs($partner)
            ->get(route('delivery.deliveries.show', $delivery))
            ->assertOk()
            ->assertSee('Cash to collect')
            ->assertSee(number_format($order->amountDue(), 2))
            ->assertSee('Smart Coins redeemed by the customer');
    }

    /**
     * Requirement 17: cancelling a real redeemed order restores the coins
     * exactly once, through the existing order-lifecycle rules.
     */
    public function test_cancellation_restores_redeemed_coins_exactly_once(): void
    {
        $buyer = $this->buyer();
        app(SmartCoinsService::class)->credit($buyer, 350, 'refund_credit', 'refund', 1, 'cancel-setup', 'seeded wallet');
        $this->cartWithWatch($buyer);
        $this->checkoutPost($buyer, ['coins_to_redeem' => 100])->assertRedirect('/checkout/complete');

        $order = Order::where('user_id', $buyer->id)->firstOrFail();
        $this->assertSame(250.0, app(SmartCoinsService::class)->balance($buyer));

        app(OrderStatusService::class)->transition($order, 'cancelled', $buyer, 'changed mind');

        // Coins come back once, ledger shows the matching restoration credit.
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame(350.0, app(SmartCoinsService::class)->balance($buyer));
        $this->assertSame(100.0, (float) $order->fresh()->coins_refunded);
        $this->assertSame(1, SmartCoinTransaction::where('user_id', $buyer->id)
            ->where('type', 'checkout_redemption')->count());
        $this->assertSame(1, SmartCoinTransaction::where('user_id', $buyer->id)
            ->where('type', 'order_cancellation_restoration')->count());
    }

    // ------------------------------------------------------------------
    // REQUIREMENTS 18 + 19 — ORDINARY CHECKOUT AND HISTORY SAFETY
    // ------------------------------------------------------------------

    /**
     * Requirement 18: coupons, tax, shipping, stock decrement and an
     * ordinary (no-coin) checkout all keep working unchanged.
     */
    public function test_coupon_tax_shipping_stock_and_plain_checkout_still_work(): void
    {
        $buyer = $this->buyer();
        Coupon::create([
            'code' => 'SAVE10',
            'type' => 'fixed',
            'value' => 10,
            'active' => true,
        ]);

        $stockBefore = (int) Product::where('slug', 'smart-watch-pro')->value('quantity');
        $this->assertGreaterThan(0, $stockBefore);

        $this->cartWithWatch($buyer);

        // Apply the coupon exactly the way the checkout page does.
        $this->actingAs($buyer)->post(route('checkout.coupon.apply'), ['coupon_code' => 'SAVE10'])
            ->assertRedirect(route('checkout.index'));

        $this->checkoutPost($buyer, ['coins_to_redeem' => 0])->assertRedirect('/checkout/complete');

        $order = Order::where('user_id', $buyer->id)->firstOrFail();
        $this->assertSame(199.00, (float) $order->subtotal);
        $this->assertSame(35.82, (float) $order->tax);
        $this->assertSame(50.00, (float) $order->shipping_cost);
        $this->assertSame(10.0, (float) $order->discount_amount);
        $this->assertSame('SAVE10', $order->coupon_code);
        // 199 + 35.82 + 50 − 10 = 274.82 (identical to the pre-fix figure
        // when no coins are involved — nothing about money maths changed).
        $this->assertSame(274.82, (float) $order->total);
        $this->assertSame(0.0, (float) $order->coins_redeemed);
        $this->assertSame(274.82, $order->amountDue());

        // Stock was decremented exactly once and the cart was emptied.
        $this->assertSame($stockBefore - 1, (int) Product::where('slug', 'smart-watch-pro')->value('quantity'));
        $this->assertSame([], app(CartService::class)->lines());
    }

    /**
     * Requirement 19: historical orders are handled safely — a legacy order
     * without coins renders exactly as before, and a PRE-FIX coin order
     * (gross total + coins_redeemed, the user's screenshot shape:
     * 189 + 50 + 34.02 = 273.02 gross − 223 coins = 50.02 payable) displays
     * the correct payable WITHOUT rewriting the stored row.
     */
    public function test_historical_orders_remain_correct_without_rewrites(): void
    {
        $buyer = $this->buyer();

        // (a) Legacy order with NO coin redemption: unchanged behaviour.
        $plain = $this->makeLegacyOrder($buyer, [
            'order_number' => 'KDP-LEGACY-PLAIN',
            'subtotal' => 100.00,
            'tax' => 18.00,
            'shipping_cost' => 50.00,
            'total' => 168.00,
        ]);
        $this->assertSame(168.0, $plain->amountDue());
        $this->assertSame(168.0, (float) $plain->total);
        $this->assertSame(168.0, $plain->originalTotal());

        // (b) Legacy PRE-FIX coin order: `total` still holds the gross,
        // but every screen derives the true payable (50.02) from the
        // stable columns — no historical data is invented or mutated.
        $legacy = $this->makeLegacyOrder($buyer, [
            'order_number' => 'KDP-LEGACY-COINS',
            'coins_redeemed' => 223.00,
        ]);
        $this->assertSame(273.02, $legacy->originalTotal());
        $this->assertSame(223.0, $legacy->coinsValue());
        $this->assertSame(50.02, $legacy->amountDue());

        $this->actingAs($buyer)->get(route('orders.show', $legacy))
            ->assertOk()
            ->assertSee('50.02')
            ->assertSee('Smart Coins discount (223 coins)');

        // Reading the page must NOT rewrite the historical row.
        $legacy->refresh();
        $this->assertSame(273.02, (float) $legacy->total, 'Historical total never rewritten.');
        $this->assertSame(223.0, (float) $legacy->coins_redeemed);
    }
}
