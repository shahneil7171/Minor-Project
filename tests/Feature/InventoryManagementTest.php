<?php

namespace Tests\Feature;

use App\Models\InventoryTransaction;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ReturnRequest;
use App\Models\User;
use App\Services\InventoryService;
use App\Services\ProductVariantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * PHASE 3 — Complete inventory & stock management.
 *
 * Covers basic stock, cart validation, checkout revalidation, variants,
 * concurrency, cancellation, returns, authorization and the inventory history.
 */
class InventoryManagementTest extends TestCase
{
    use RefreshDatabase;

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function buyer(): User
    {
        return User::factory()->create(['account_type' => 'buyer', 'status' => 'active']);
    }

    private function seller(): User
    {
        return User::factory()->create(['account_type' => 'seller', 'status' => 'active']);
    }

    private function admin(): User
    {
        return User::factory()->create(['account_type' => 'admin', 'status' => 'active']);
    }

    private function partner(): User
    {
        return User::factory()->create(['account_type' => 'delivery_partner', 'status' => 'active']);
    }

    private function inventory(): InventoryService
    {
        return app(InventoryService::class);
    }

    /**
     * A plain (variant-free) product owned by the given seller.
     */
    private function makeProduct(int $quantity = 10, ?User $seller = null, string $slug = 'inventory-charger'): Product
    {
        return Product::create([
            'slug'         => $slug,
            'title'        => 'Samsung 25W Charger',
            'sku'          => 'CHARGER-001',
            'description'  => 'Fast charger',
            'price'        => 999,
            'quantity'     => $quantity,
            'stock_status' => 'in-stock',
            'tax'          => 0,
            'status'       => 1,
            'tags'         => [],
            'options'      => [],
            'variants'     => [],
            'is_seed'      => false,
            'seller_id'    => $seller?->id,
        ]);
    }

    /**
     * A two-variant product (Purple/256GB and Black/512GB).
     *
     * @return array{0: Product, 1: array<string, string>} key => variant id
     */
    private function makeVariantProduct(int $purpleStock = 5, int $blackStock = 10, ?User $seller = null): array
    {
        $purple = ['Color' => 'Purple', 'Storage' => '256GB'];
        $black = ['Color' => 'Black', 'Storage' => '512GB'];

        $product = Product::create([
            'slug'         => 'samsung-s26-ultra',
            'title'        => 'Samsung S26 Ultra',
            'sku'          => 'SAM-S26U-001',
            'description'  => 'Flagship',
            'price'        => 119999,
            'quantity'     => 30,
            'stock_status' => 'in-stock',
            'tax'          => 0,
            'status'       => 1,
            'tags'         => [],
            'options'      => [
                ['name' => 'Color', 'values' => ['Purple', 'Black']],
                ['name' => 'Storage', 'values' => ['256GB', '512GB']],
            ],
            'variants'     => [
                [
                    'id'     => ProductVariantService::variantId($purple),
                    'values' => $purple,
                    'sku'    => 'S26U-PURPLE-256',
                    'price'  => 119999,
                    'stock'  => $purpleStock,
                ],
                [
                    'id'     => ProductVariantService::variantId($black),
                    'values' => $black,
                    'sku'    => 'S26U-BLACK-512',
                    'price'  => 129999,
                    'stock'  => $blackStock,
                ],
            ],
            'is_seed'      => false,
            'seller_id'    => $seller?->id,
        ]);

        return [
            $product->refresh(),
            [
                'purple' => ProductVariantService::variantId($purple),
                'black'  => ProductVariantService::variantId($black),
            ],
        ];
    }

    private function address(): array
    {
        return [
            'full_name'      => 'Alice Buyer',
            'phone'          => '9876543210',
            'house_number'   => '12/A',
            'street_address' => 'Rose Street',
            'city'           => 'Mumbai',
            'state'          => 'MH',
            'pincode'        => '400001',
            'country'        => 'India',
        ];
    }

    /**
     * Run the REAL add-to-cart + checkout flow for one product line.
     */
    private function checkout(User $buyer, string $slug, int $quantity = 1, ?string $variantId = null)
    {
        $this->actingAs($buyer)
            ->post(route('cart.add', ['product' => $slug]), array_filter([
                'quantity'   => $quantity,
                'variant_id' => $variantId,
            ]))
            ->assertRedirect(route('cart.index'));

        return $this->actingAs($buyer)->post(route('checkout.submit'), [
            'address_option'  => 'new',
            'new_address'     => $this->address(),
            'shipping_method' => 'standard',
            'payment_method'  => 'cod',
        ]);
    }

    /**
     * Create an order line and commit the matching sale, exactly like the
     * checkout transaction does (used where the cart flow is not the subject).
     */
    private function orderWithSale(User $buyer, Product $product, int $quantity = 1, ?string $variantId = null, string $status = 'pending'): Order
    {
        $order = Order::create([
            'user_id'          => $buyer->id,
            'customer_email'   => $buyer->email,
            'order_number'     => 'KDP-' . strtoupper(uniqid()),
            'status'           => $status,
            'subtotal'         => 100.0 * $quantity,
            'tax'              => 0,
            'shipping_cost'    => 0,
            'discount_amount'  => 0,
            'total'            => 100.0 * $quantity,
            'payment_method'   => 'cod',
            'shipping_name'    => $buyer->name,
            'shipping_phone'   => '9999999999',
            'shipping_address' => '123 Main St',
            'shipping_city'    => 'Mumbai',
            'shipping_state'   => 'MH',
            'shipping_pincode' => '400001',
        ]);

        $item = $order->items()->create([
            'product_slug'  => $product->slug,
            'product_id'    => $product->id,
            'variant_id'    => $variantId,
            'product_title' => $product->title,
            'sku'           => $variantId ? 'VAR-1' : $product->sku,
            'price'         => 100,
            'quantity'      => $quantity,
            'subtotal'      => 100 * $quantity,
            'seller_id'     => $product->seller_id,
        ]);

        $this->inventory()->commitSale($product, $variantId, $quantity, [
            'order_id'      => $order->id,
            'order_item_id' => $item->id,
            'reference'     => $order->order_number,
        ]);

        return $order->refresh();
    }

    // ------------------------------------------------------------------
    // TEST 1-5: basic product stock
    // ------------------------------------------------------------------

    public function test_a_product_with_stock_ten_reports_ten_available(): void
    {
        $product = $this->makeProduct(10);

        $this->assertSame(10, $this->inventory()->stockFor($product));
        $this->assertSame(0, $this->inventory()->reservedFor($product));
        $this->assertSame(10, $this->inventory()->availableFor($product));
        $this->assertTrue($this->inventory()->hasStock($product, null, 10));
        $this->assertFalse($this->inventory()->hasStock($product, null, 11));
        $this->assertSame(InventoryService::STATUS_IN_STOCK, $this->inventory()->statusFor($product));
    }

    public function test_buying_three_units_decreases_stock_and_available(): void
    {
        $buyer = $this->buyer();
        $product = $this->makeProduct(10);

        $this->checkout($buyer, $product->slug, 3)->assertRedirect(route('checkout.complete'));

        $product->refresh();
        $this->assertSame(7, $this->inventory()->stockFor($product));
        $this->assertSame(7, $this->inventory()->availableFor($product));

        $sale = InventoryTransaction::where('type', 'sale')->firstOrFail();
        $this->assertSame(-3, $sale->quantity);
        $this->assertSame(10, $sale->previous_stock);
        $this->assertSame(7, $sale->new_stock);
        $this->assertSame($product->id, $sale->product_id);
    }

    public function test_buying_more_than_the_available_stock_is_rejected(): void
    {
        $buyer = $this->buyer();
        $product = $this->makeProduct(2);

        $this->actingAs($buyer)
            ->post(route('cart.add', ['product' => $product->slug]), ['quantity' => 3])
            ->assertRedirect(route('cart.index'))
            ->assertSessionHas('error');

        $this->assertEmpty(app(\App\Services\CartService::class)->lines());
        $this->assertSame(2, $product->refresh()->quantity);
    }

    public function test_stock_reaching_zero_marks_the_product_out_of_stock(): void
    {
        $buyer = $this->buyer();
        $product = $this->makeProduct(2);

        $this->checkout($buyer, $product->slug, 2)->assertRedirect(route('checkout.complete'));

        $product->refresh();
        $this->assertSame(0, $this->inventory()->availableFor($product));
        $this->assertSame(InventoryService::STATUS_OUT_OF_STOCK, $this->inventory()->statusFor($product));
        $this->assertFalse($this->inventory()->isPurchasable($product));
        $this->assertSame('Out of Stock', $this->inventory()->storefrontLabel($product));
    }

    public function test_an_out_of_stock_product_cannot_be_purchased(): void
    {
        $buyer = $this->buyer();
        $product = $this->makeProduct(0);

        $this->actingAs($buyer)
            ->post(route('cart.add', ['product' => $product->slug]), ['quantity' => 1])
            ->assertRedirect(route('cart.index'))
            ->assertSessionHas('error');

        $this->actingAs($buyer)
            ->post(route('cart.buy-now', ['product' => $product->slug]), ['quantity' => 1])
            ->assertRedirect(route('cart.index'))
            ->assertSessionHas('error');

        // The wishlist stays available even when the product cannot be bought.
        $this->actingAs($buyer)
            ->post(route('wishlist.toggle', ['product' => $product->slug]))
            ->assertRedirect();
        $this->assertDatabaseHas('wishlist_items', [
            'user_id' => $buyer->id,
            'product_slug' => $product->slug,
        ]);
    }

    // ------------------------------------------------------------------
    // TEST 6-7: cart validation + checkout re-validation
    // ------------------------------------------------------------------

    public function test_a_cart_holding_the_whole_stock_rejects_one_more_unit(): void
    {
        $buyer = $this->buyer();
        $product = $this->makeProduct(5);

        $this->actingAs($buyer)
            ->post(route('cart.add', ['product' => $product->slug]), ['quantity' => 5])
            ->assertRedirect(route('cart.index'));

        // 5 already in the cart + 1 more = 6 > 5 available.
        $this->actingAs($buyer)
            ->post(route('cart.add', ['product' => $product->slug]), ['quantity' => 1])
            ->assertRedirect(route('cart.index'))
            ->assertSessionHas('error', 'Only 5 units are available.');

        $this->assertSame(5, (int) app(\App\Services\CartService::class)->lines()[$product->slug]['quantity']);
    }

    public function test_checkout_revalidates_inventory_and_fails_when_stock_sold_away(): void
    {
        $buyer = $this->buyer();
        $rival = $this->buyer();
        $product = $this->makeProduct(5);

        $this->actingAs($buyer)
            ->post(route('cart.add', ['product' => $product->slug]), ['quantity' => 4])
            ->assertRedirect(route('cart.index'));

        // Another shopper takes 3 of the 5 units while our cart sits there.
        $this->actingAs($rival)
            ->post(route('cart.add', ['product' => $product->slug]), ['quantity' => 3]);
        $this->orderWithSale($rival, $product->refresh(), 3);

        $this->assertSame(2, $this->inventory()->availableFor($product->refresh()));

        $response = $this->actingAs($buyer)->post(route('checkout.submit'), [
            'address_option'  => 'new',
            'new_address'     => $this->address(),
            'shipping_method' => 'standard',
            'payment_method'  => 'cod',
        ]);

        $response->assertRedirect(route('cart.index'))->assertSessionHas('error');
        $this->assertSame(0, $buyer->orders()->count(), 'No order may exist after a failed checkout.');
        $this->assertSame(2, $product->refresh()->quantity, 'A failed checkout must not touch stock.');
    }

    public function test_increasing_a_cart_line_revalidates_against_real_stock(): void
    {
        $buyer = $this->buyer();
        $product = $this->makeProduct(5);

        $this->actingAs($buyer)
            ->post(route('cart.add', ['product' => $product->slug]), ['quantity' => 2]);

        // Somebody else buys 4 of the 5 units: only 1 is left for our cart.
        $this->orderWithSale($this->buyer(), $product->refresh(), 4);

        $this->actingAs($buyer)
            ->post(route('cart.increase', ['product' => $product->slug]))
            ->assertRedirect(route('cart.index'))
            ->assertSessionHas('error');

        $this->assertSame(2, (int) app(\App\Services\CartService::class)->lines()[$product->slug]['quantity']);
    }

    // ------------------------------------------------------------------
    // TEST 8-9: variant stock
    // ------------------------------------------------------------------

    public function test_buying_one_variant_never_moves_the_other_variant_or_the_parent(): void
    {
        $buyer = $this->buyer();
        [$product, $variants] = $this->makeVariantProduct(5, 10);

        $this->checkout($buyer, $product->slug, 2, $variants['purple'])
            ->assertRedirect(route('checkout.complete'));

        $product->refresh();
        $this->assertSame(3, $this->inventory()->stockFor($product, $variants['purple']));
        $this->assertSame(10, $this->inventory()->stockFor($product, $variants['black']));
        $this->assertSame(30, $product->quantity, 'The parent quantity is not the variant inventory source.');

        $sale = InventoryTransaction::where('type', 'sale')->firstOrFail();
        $this->assertSame($variants['purple'], $sale->product_variant_id);
    }

    public function test_a_zero_stock_variant_blocks_purchase_but_keeps_the_wishlist(): void
    {
        $buyer = $this->buyer();
        [$product, $variants] = $this->makeVariantProduct(0, 10);

        $this->actingAs($buyer)
            ->post(route('cart.add', ['product' => $product->slug]), [
                'quantity'   => 1,
                'variant_id' => $variants['purple'],
            ])
            ->assertRedirect(route('cart.index'))
            ->assertSessionHas('error');

        $this->actingAs($buyer)
            ->post(route('cart.buy-now', ['product' => $product->slug]), [
                'quantity'   => 1,
                'variant_id' => $variants['purple'],
            ])
            ->assertRedirect(route('cart.index'))
            ->assertSessionHas('error');

        // The healthy variant is still purchasable.
        $this->actingAs($buyer)
            ->post(route('cart.add', ['product' => $product->slug]), [
                'quantity'   => 1,
                'variant_id' => $variants['black'],
            ])
            ->assertRedirect(route('cart.index'))
            ->assertSessionHas('success');

        $this->actingAs($buyer)
            ->post(route('wishlist.toggle', ['product' => $product->slug]))
            ->assertRedirect();
        $this->assertDatabaseHas('wishlist_items', [
            'user_id'       => $buyer->id,
            'product_slug'  => $product->slug,
        ]);
    }

    // ------------------------------------------------------------------
    // Concurrency: two buyers racing for the last unit
    // ------------------------------------------------------------------

    public function test_two_simultaneous_purchases_of_the_last_unit_cannot_oversell(): void
    {
        $product = $this->makeProduct(1);
        $service = $this->inventory();

        $first = null;
        $second = null;

        // Two independent "buyers" each try to take the single remaining unit.
        try {
            $first = $service->commitSale($product->fresh(), null, 1, ['reference' => 'ORDER-A']);
        } catch (ValidationException $e) {
            $first = $e;
        }

        try {
            $second = $service->commitSale($product->fresh(), null, 1, ['reference' => 'ORDER-B']);
        } catch (ValidationException $e) {
            $second = $e;
        }

        $this->assertInstanceOf(InventoryTransaction::class, $first, 'The first purchase must succeed.');
        $this->assertInstanceOf(ValidationException::class, $second, 'The second purchase must fail safely.');
        $this->assertSame(
            'Only 0 units of Samsung 25W Charger are available.',
            collect($second->errors())->flatten()->first(),
        );

        $this->assertSame(0, $product->refresh()->quantity, 'Stock may never go negative.');
        $this->assertSame(1, InventoryTransaction::where('type', 'sale')->count());
    }

    public function test_inventory_rolls_back_when_the_surrounding_order_transaction_fails(): void
    {
        $product = $this->makeProduct(10);
        $service = $this->inventory();

        // Rule: if order creation fails AFTER the stock was taken, the database
        // transaction must roll the inventory change back — never leaving stock
        // permanently reduced by an order that does not exist.
        try {
            DB::transaction(function () use ($service, $product) {
                $service->commitSale($product, null, 3, ['reference' => 'ROLLBACK']);

                throw new \RuntimeException('order creation failed after the stock was taken');
            });

            $this->fail('The simulated order failure should have propagated.');
        } catch (\RuntimeException $e) {
            $this->assertSame('order creation failed after the stock was taken', $e->getMessage());
        }

        $this->assertSame(10, $product->fresh()->quantity, 'A failed order must never leave stock reduced.');
        $this->assertSame(0, InventoryTransaction::where('type', 'sale')->count());
        $this->assertSame(0, InventoryTransaction::count());
    }

    // ------------------------------------------------------------------
    // Cancellation: restore exactly once
    // ------------------------------------------------------------------

    public function test_cancelling_an_order_restores_the_stock_exactly_once(): void
    {
        $buyer = $this->buyer();
        $product = $this->makeProduct(10);
        $order = $this->orderWithSale($buyer, $product, 2);

        $this->assertSame(8, $product->refresh()->quantity);

        $this->actingAs($buyer)
            ->post(route('orders.cancel', ['order' => $order->id]))
            ->assertRedirect();

        $this->assertSame('cancelled', $order->refresh()->status);
        $this->assertSame(10, $product->refresh()->quantity, 'The cancelled units must come back.');

        // A second cancellation attempt (double click / replayed request) must
        // not restore the stock twice.
        $this->inventory()->restoreCancelledOrder($order->fresh());
        $this->assertSame(10, $product->refresh()->quantity, 'Stock must never be restored twice.');

        $this->assertSame(1, InventoryTransaction::where('type', 'cancellation')->count());

        $restore = InventoryTransaction::where('type', 'cancellation')->firstOrFail();
        $this->assertSame(2, $restore->quantity);
        $this->assertSame(8, $restore->previous_stock);
        $this->assertSame(10, $restore->new_stock);
    }

    public function test_an_order_without_a_recorded_sale_never_creates_phantom_stock(): void
    {
        $buyer = $this->buyer();
        $product = $this->makeProduct(10);

        // A legacy order placed before inventory tracking existed: no `sale`
        // transaction, so cancelling it must not invent stock.
        $order = Order::create([
            'user_id'          => $buyer->id,
            'customer_email'   => $buyer->email,
            'order_number'     => 'KDP-LEGACY-1',
            'status'           => 'pending',
            'subtotal'         => 200,
            'tax'              => 0,
            'shipping_cost'    => 0,
            'total'            => 200,
            'payment_method'   => 'cod',
            'shipping_name'    => $buyer->name,
            'shipping_phone'   => '9999999999',
            'shipping_address' => '123 Main St',
            'shipping_city'    => 'Mumbai',
            'shipping_state'   => 'MH',
            'shipping_pincode' => '400001',
        ]);
        $order->items()->create([
            'product_slug'  => $product->slug,
            'product_id'    => $product->id,
            'product_title' => $product->title,
            'price'         => 100,
            'quantity'      => 2,
            'subtotal'      => 200,
        ]);

        $this->actingAs($buyer)->post(route('orders.cancel', ['order' => $order->id]));

        $this->assertSame(10, $product->refresh()->quantity);
        $this->assertSame(0, InventoryTransaction::where('type', 'cancellation')->count());
    }

    // ------------------------------------------------------------------
    // Returns: no automatic restoration, resellable only, exactly once
    // ------------------------------------------------------------------

    private function makeReturn(Product $product, OrderItem $item, User $seller, User $buyer, string $status, int $quantity = 1): ReturnRequest
    {
        return ReturnRequest::create([
            'order_id'          => $item->order_id,
            'order_item_id'     => $item->id,
            'user_id'           => $buyer->id,
            'order_number'      => $item->order->order_number,
            'customer_email'    => $buyer->email,
            'product_slug'      => $product->slug,
            'product_id'        => $product->id,
            'product_variant_id'=> $item->variant_id,
            'seller_id'         => $seller->id,
            'product_title'     => $product->title,
            'quantity'          => $quantity,
            'reason'            => 'Product damaged',
            'status'            => $status,
            'refund_status'     => $status === 'refunded' ? 'refunded' : 'none',
            'refund_amount'     => 100,
            'requested_at'      => now(),
        ]);
    }

    public function test_a_return_never_restores_stock_before_it_is_inspected_as_resellable(): void
    {
        $buyer = $this->buyer();
        $seller = $this->seller();
        $admin = $this->admin();
        $product = $this->makeProduct(10, $seller);

        $order = $this->orderWithSale($buyer, $product, 2, null, 'delivered');
        $this->assertSame(8, $product->refresh()->quantity);

        $item = $order->items()->first();
        $return = $this->makeReturn($product, $item, $seller, $buyer, 'pending');
        $returns = app(\App\Services\ReturnService::class);

        // Requested.
        $this->assertSame(8, $product->refresh()->quantity);

        // Approved.
        $returns->approve($return, $admin);
        $this->assertSame(8, $product->refresh()->quantity);

        // Pickup assigned + collected.
        $partner = $this->partner();
        $returns->schedulePickup($return, $partner->id, $admin);
        $returns->markPickedUp($return, $partner);
        $this->assertSame(8, $product->refresh()->quantity);

        // Received — still pending inspection, so still no restoration.
        $returns->markReceived($return, $admin);
        $this->assertSame(8, $product->refresh()->quantity);
        $this->assertSame(0, InventoryTransaction::where('type', 'return_restock')->count());
    }

    public function test_a_resellable_return_is_restocked_exactly_once(): void
    {
        $buyer = $this->buyer();
        $seller = $this->seller();
        $admin = $this->admin();
        $product = $this->makeProduct(10, $seller);

        $order = $this->orderWithSale($buyer, $product, 2, null, 'delivered');
        $return = $this->makeReturn($product, $order->items()->first(), $seller, $buyer, 'received', 2);

        $this->actingAs($admin)
            ->post(route('admin.returns.restock', $return), ['inventory_condition' => 'resellable'])
            ->assertRedirect();

        $this->assertSame(10, $product->refresh()->quantity);
        $this->assertNotNull($return->refresh()->restocked_at);
        $this->assertSame('resellable', $return->inventory_condition);

        // Pressing "Restock" again must not add the units a second time.
        $this->actingAs($admin)
            ->post(route('admin.returns.restock', $return), ['inventory_condition' => 'resellable'])
            ->assertSessionHas('error');

        $this->assertSame(10, $product->refresh()->quantity, 'A return must never be restocked twice.');
        $this->assertSame(1, InventoryTransaction::where('type', 'return_restock')->count());

        $restock = InventoryTransaction::where('type', 'return_restock')->firstOrFail();
        $this->assertSame(2, $restock->quantity);
        $this->assertSame(8, $restock->previous_stock);
        $this->assertSame(10, $restock->new_stock);
        $this->assertSame($return->id, $restock->return_request_id);
    }

    public function test_a_damaged_return_never_goes_back_into_sellable_stock(): void
    {
        $buyer = $this->buyer();
        $seller = $this->seller();
        $admin = $this->admin();
        $product = $this->makeProduct(10, $seller);

        $order = $this->orderWithSale($buyer, $product, 2, null, 'delivered');
        $return = $this->makeReturn($product, $order->items()->first(), $seller, $buyer, 'received', 2);

        $this->actingAs($admin)
            ->post(route('admin.returns.restock', $return), [
                'inventory_condition' => 'damaged',
                'note'                => 'Screen is cracked',
            ])
            ->assertRedirect();

        $this->assertSame(8, $product->refresh()->quantity, 'Damaged units must not become sellable stock.');
        $this->assertSame('damaged', $return->refresh()->inventory_condition);
        $this->assertNull($return->restocked_at);
        $this->assertSame(0, InventoryTransaction::where('type', 'return_restock')->count());
    }

    // ------------------------------------------------------------------
    // Authorization
    // ------------------------------------------------------------------

    public function test_a_seller_cannot_change_another_sellers_inventory(): void
    {
        $sellerA = $this->seller();
        $sellerB = $this->seller();
        $product = $this->makeProduct(10, $sellerB);

        $this->actingAs($sellerA)
            ->post(route('seller.inventory.adjust', ['product' => $product->id]), [
                'quantity' => 5,
                'reason'   => 'Trying to steal stock',
            ])
            ->assertForbidden();

        $this->assertSame(10, $product->refresh()->quantity);
    }

    public function test_a_seller_can_adjust_their_own_inventory_and_it_is_audited(): void
    {
        $seller = $this->seller();
        $product = $this->makeProduct(10, $seller);

        $this->actingAs($seller)
            ->post(route('seller.inventory.adjust', ['product' => $product->id]), [
                'quantity' => 5,
                'reason'   => 'Physical stock received',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(15, $product->refresh()->quantity);

        $transaction = InventoryTransaction::where('type', 'manual_adjustment')->firstOrFail();
        $this->assertSame(5, $transaction->quantity);
        $this->assertSame(10, $transaction->previous_stock);
        $this->assertSame(15, $transaction->new_stock);
        $this->assertSame($seller->id, $transaction->actor_id);
        $this->assertSame($seller->id, $transaction->seller_id);
        $this->assertSame('Physical stock received', $transaction->reason);
    }

    public function test_buyers_and_delivery_partners_can_never_reach_the_inventory_actions(): void
    {
        $seller = $this->seller();
        $product = $this->makeProduct(10, $seller);

        foreach ([$this->buyer(), $this->partner()] as $user) {
            $this->actingAs($user)
                ->get(route('seller.inventory.index'))
                ->assertForbidden();

            $this->actingAs($user)
                ->post(route('seller.inventory.adjust', ['product' => $product->id]), [
                    'quantity' => 1,
                    'reason'   => 'nope',
                ])
                ->assertForbidden();
        }

        $this->assertSame(10, $product->refresh()->quantity);
    }

    public function test_an_admin_can_adjust_any_inventory_with_a_reason(): void
    {
        $admin = $this->admin();
        $seller = $this->seller();
        $product = $this->makeProduct(10, $seller);

        $this->actingAs($admin)
            ->post(route('admin.inventory.adjust', ['product' => $product->id]), [
                'quantity' => -2,
                'reason'   => 'Damaged stock',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(8, $product->refresh()->quantity);

        // A reason is mandatory, and the quantity must be a whole number.
        $this->actingAs($admin)
            ->post(route('admin.inventory.adjust', ['product' => $product->id]), ['quantity' => 1])
            ->assertSessionHasErrors('reason');

        $this->actingAs($admin)
            ->post(route('admin.inventory.adjust', ['product' => $product->id]), [
                'quantity' => 2.5,
                'reason'   => 'Fractional stock is nonsense',
            ])
            ->assertSessionHasErrors('quantity');

        $this->assertSame(8, $product->refresh()->quantity);
    }

    public function test_stock_can_never_be_driven_negative(): void
    {
        $seller = $this->seller();
        $product = $this->makeProduct(2, $seller);

        $this->actingAs($seller)
            ->post(route('seller.inventory.adjust', ['product' => $product->id]), [
                'quantity' => -5,
                'reason'   => 'Trying to write off more than exists',
            ])
            ->assertSessionHas('error');

        $this->assertSame(2, $product->fresh()->quantity);

        $this->expectException(ValidationException::class);
        $this->inventory()->commitSale($product->fresh(), null, 3);
    }

    public function test_price_and_category_changes_never_touch_inventory(): void
    {
        $seller = $this->seller();
        $product = $this->makeProduct(10, $seller);

        $this->actingAs($seller)
            ->post(route('products.update', ['product' => $product->slug]), [
                'title'        => 'Samsung 25W Charger (updated)',
                'description'  => 'Fast charger, now with a longer cable',
                'price'        => 1299,
                'quantity'     => 10,
                'stock_status' => 'in-stock',
                'category'     => 'Electronics',
                'status'       => 1,
            ])
            ->assertRedirect();

        $this->assertSame(10, $product->fresh()->quantity);
        $this->assertSame(1299.0, (float) $product->fresh()->price);
        $this->assertSame(0, InventoryTransaction::where('type', 'manual_adjustment')->count());
    }

    public function test_editing_the_quantity_in_the_product_form_is_recorded_as_an_adjustment(): void
    {
        $seller = $this->seller();
        $product = $this->makeProduct(10, $seller);

        $this->actingAs($seller)
            ->post(route('products.update', ['product' => $product->slug]), [
                'title'        => 'Samsung 25W Charger',
                'description'  => 'Fast charger',
                'price'        => 999,
                'quantity'     => 15,
                'stock_status' => 'in-stock',
                'category'     => 'Electronics',
                'status'       => 1,
            ])
            ->assertRedirect();

        $this->assertSame(15, $product->fresh()->quantity);

        $adjustment = InventoryTransaction::where('type', 'manual_adjustment')->firstOrFail();
        $this->assertSame(5, $adjustment->quantity);
        $this->assertSame(10, $adjustment->previous_stock);
        $this->assertSame(15, $adjustment->new_stock);
        $this->assertSame($seller->id, $adjustment->actor_id);
    }

    // ------------------------------------------------------------------
    // Inventory history and low-stock alerts
    // ------------------------------------------------------------------

    public function test_every_stock_changing_event_is_recorded_with_full_audit_data(): void
    {
        $seller = $this->seller();
        $admin = $this->admin();
        $buyer = $this->buyer();
        $product = $this->makeProduct(10, $seller);

        $this->inventory()->recordInitialStock($product, $seller, 'Product created');
        $this->assertSame(1, InventoryTransaction::where('type', 'initial_stock')->count());

        $order = $this->orderWithSale($buyer, $product, 2);

        $this->actingAs($buyer)->post(route('orders.cancel', ['order' => $order->id]));

        $this->actingAs($admin)
            ->post(route('admin.inventory.adjust', ['product' => $product->id]), [
                'quantity' => 2,
                'reason'   => 'Physical stock received',
            ]);

        $history = array_values(InventoryTransaction::orderBy('id')->pluck('type')->all());

        $this->assertSame(['initial_stock', 'sale', 'cancellation', 'manual_adjustment'], $history);

        $initial = InventoryTransaction::where('type', 'initial_stock')->firstOrFail();
        $this->assertSame(10, $initial->quantity);
        $this->assertSame(0, $initial->previous_stock);
        $this->assertSame(10, $initial->new_stock);
        $this->assertSame('Product created', $initial->reference);

        $sale = InventoryTransaction::where('type', 'sale')->firstOrFail();
        $this->assertSame($order->id, $sale->order_id);
        $this->assertNotNull($sale->order_item_id);
        $this->assertSame($order->order_number, $sale->reference);
        $this->assertSame($seller->id, $sale->seller_id);

        $this->assertSame(12, $product->fresh()->quantity);
    }

    public function test_crossing_into_low_stock_notifies_the_seller_exactly_once(): void
    {
        $seller = $this->seller();
        $product = $this->makeProduct(10, $seller);

        $this->assertSame(5, $this->inventory()->thresholdFor($product->fresh()), 'Default low-stock threshold.');

        // 10 -> 4 crosses the default threshold of 5: exactly one alert.
        $this->inventory()->commitSale($product->fresh(), null, 6, ['reference' => 'ORDER-1']);
        $this->assertSame(4, $product->fresh()->quantity);

        $titles = $this->notificationTitlesFor($seller);
        $this->assertCount(1, $titles, 'Crossing into low stock must notify the seller exactly once.');
        $this->assertSame('Low Stock Alert', $titles[0]);

        // Still low, but no new crossing -> no notification spam.
        $this->inventory()->commitSale($product->fresh(), null, 1, ['reference' => 'ORDER-2']);
        $this->assertCount(1, $this->notificationTitlesFor($seller), 'No alert while the product simply stays low.');

        // A product that drops straight to zero raises the out-of-stock alert.
        $second = $this->makeProduct(2, $seller, 'second-product');
        $second->update(['low_stock_threshold' => 0]);
        $this->inventory()->commitSale($second->fresh(), null, 2, ['reference' => 'ORDER-3']);

        $titles = $this->notificationTitlesFor($seller);
        $this->assertCount(2, $titles);
        $this->assertSame('Out of Stock Alert', $titles[1]);
    }

    /**
     * The in-app alert titles a user has received (the real database
     * notification channel, not a fake).
     *
     * @return array<int, string>
     */
    private function notificationTitlesFor(User $user): array
    {
        return DB::table('notifications')
            ->where('notifiable_type', User::class)
            ->where('notifiable_id', $user->id)
            ->orderBy('created_at')
            ->get()
            ->map(fn ($row) => json_decode($row->data, true)['title'] ?? '')
            ->values()
            ->all();
    }

    public function test_a_multi_seller_order_updates_each_sellers_inventory_separately(): void
    {
        $sellerA = $this->seller();
        $sellerB = $this->seller();
        $chair = $this->makeProduct(5, $sellerA, 'ergonomic-chair');
        $keyboard = $this->makeProduct(9, $sellerB, 'mechanical-keyboard');

        $buyer = $this->buyer();
        $this->actingAs($buyer)
            ->post(route('cart.add', ['product' => $chair->slug]), ['quantity' => 1]);
        $this->actingAs($buyer)
            ->post(route('cart.add', ['product' => $keyboard->slug]), ['quantity' => 2]);

        $this->actingAs($buyer)->post(route('checkout.submit'), [
            'address_option'  => 'new',
            'new_address'     => $this->address(),
            'shipping_method' => 'standard',
            'payment_method'  => 'cod',
        ])->assertRedirect(route('checkout.complete'));

        $this->assertSame(4, $chair->refresh()->quantity);
        $this->assertSame(7, $keyboard->refresh()->quantity);

        $chairSale = InventoryTransaction::where('product_id', $chair->id)->where('type', 'sale')->firstOrFail();
        $keyboardSale = InventoryTransaction::where('product_id', $keyboard->id)->where('type', 'sale')->firstOrFail();

        $this->assertSame(-1, $chairSale->quantity);
        $this->assertSame($sellerA->id, $chairSale->seller_id);
        $this->assertSame(-2, $keyboardSale->quantity);
        $this->assertSame($sellerB->id, $keyboardSale->seller_id);
    }

    public function test_the_seller_inventory_page_only_shows_their_own_products(): void
    {
        $sellerA = $this->seller();
        $sellerB = $this->seller();
        $this->makeProduct(10, $sellerA, 'my-product');
        $this->makeProduct(3, $sellerB, 'their-product');

        $this->actingAs($sellerA)
            ->get(route('seller.inventory.index'))
            ->assertOk()
            ->assertSee('Samsung 25W Charger')
            ->assertDontSee('their-product');
    }

    public function test_the_inventory_pages_render_status_badges_and_empty_states(): void
    {
        $seller = $this->seller();
        $this->makeProduct(0, $seller, 'sold-out-product');

        $this->actingAs($seller)
            ->get(route('seller.inventory.index'))
            ->assertOk()
            ->assertSee('Out of Stock')
            ->assertSee('Low Stock')
            ->assertSee('In Stock');

        $this->actingAs($seller)
            ->get(route('seller.inventory.index', ['status' => 'out_of_stock']))
            ->assertOk()
            ->assertSee('Out of Stock');

        // A filter that matches nothing renders the empty state, not an error.
        $this->actingAs($seller)
            ->get(route('seller.inventory.index', ['status' => 'in_stock']))
            ->assertOk()
            ->assertSee('No inventory items found.');

        $this->actingAs($seller)
            ->get(route('seller.inventory.history'))
            ->assertOk()
            ->assertSee('No inventory transactions yet.');

        $this->actingAs($this->admin())
            ->get(route('admin.inventory.index'))
            ->assertOk()
            ->assertSee('Total inventory items')
            ->assertSee('Inventory transactions');

        $this->actingAs($this->admin())
            ->get(route('admin.inventory.transactions'))
            ->assertOk()
            ->assertSee('No inventory transactions yet.');

        $this->actingAs($seller)
            ->get(route('admin.inventory.index'))
            ->assertForbidden();
    }

    public function test_the_product_page_shows_availability_and_blocks_out_of_stock_purchases(): void
    {
        $buyer = $this->buyer();
        $this->makeProduct(20, null, 'plenty-product');
        $this->makeProduct(0, null, 'gone-product');

        $this->actingAs($buyer)
            ->get(route('product.show', ['product' => 'plenty-product']))
            ->assertOk()
            ->assertSee('20 units available');

        $this->actingAs($buyer)
            ->get(route('product.show', ['product' => 'gone-product']))
            ->assertOk()
            ->assertSee('Out of Stock')
            ->assertSee('wishlist');
    }
}
