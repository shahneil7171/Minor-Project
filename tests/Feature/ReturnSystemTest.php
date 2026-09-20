<?php

namespace Tests\Feature;

use App\Mail\ReturnApprovedMail;
use App\Mail\ReturnPickupAssignedMail;
use App\Mail\ReturnRejectedMail;
use App\Mail\ReturnRequestedMail;
use App\Models\Order;
use App\Models\OrderDelivery;
use App\Models\OrderItem;
use App\Models\ReturnRequest;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Return & Refund feature tests (TEST 1-17).
 *
 * Covers the buyer return window rules (from the ACTUAL delivery date),
 * quantity locking, refund calculation from recorded order data, the admin
 * workflow, role isolation and the notification/email side effects.
 */
class ReturnSystemTest extends TestCase
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
            'subtotal' => 100.00,
            'discount_amount' => 0,
            'coupon_code' => null,
            'tax' => 0,
            'shipping_cost' => 0,
            'total' => 100.00,
            'payment_method' => 'cod',
            'shipping_name' => $user->name,
            'shipping_phone' => '9999999999',
            'shipping_address' => '123 Main St',
            'shipping_city' => 'Springfield',
            'shipping_state' => 'IL',
            'shipping_pincode' => '10001',
        ], $overrides));
    }

    private function addItem(Order $order, ?User $seller = null, float $price = 100.00, int $quantity = 1, string $slug = 'test-product'): OrderItem
    {
        return $order->items()->create([
            'product_slug' => $slug,
            'product_title' => 'IKEA MARKUS Chair',
            'product_image' => null,
            'sku' => 'TP-001',
            'price' => $price,
            'quantity' => $quantity,
            'subtotal' => $price * $quantity,
            'options_text' => null,
            'seller_id' => $seller?->id,
        ]);
    }

    /**
     * Move an order through a real delivery assignment to "delivered".
     */
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

    private function submitReturn(User $buyer, OrderItem $item, array $data = [])
    {
        return $this->actingAs($buyer)->post(route('returns.store', ['item' => $item->id]), array_merge([
            'reason' => 'Product damaged',
            'description' => 'The product arrived with a damaged armrest.',
            'quantity' => 1,
        ], $data));
    }

    private function createReturn(OrderItem $item, User $buyer, string $status = 'pending'): ReturnRequest
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
            'quantity' => 1,
            'reason' => 'Product damaged',
            'status' => $status,
            'refund_status' => $status === 'refunded' ? 'refunded' : ($status === 'pending' ? 'none' : 'pending'),
            'refund_amount' => $item->price,
            'return_deadline' => $item->order->returnDeadline(),
            'requested_at' => now(),
        ]);
    }

    // ------------------------------------------------------------------
    // TEST 1-2: return window (from the ACTUAL delivery date)
    // ------------------------------------------------------------------

    public function test_return_button_available_within_window_of_delivery(): void
    {
        $buyer = $this->buyer();
        $order = $this->makeOrder($buyer);
        $item = $this->addItem($order, $this->seller());

        $this->deliverOrder($order, now()->subDays(2));

        $this->actingAs($buyer)
            ->get(route('orders.show', ['order' => $order]))
            ->assertOk()
            ->assertSee('Return Product')
            ->assertSee('Return available until');

        $this->actingAs($buyer)
            ->get(route('returns.create', ['item' => $item->id]))
            ->assertOk()
            ->assertSee('Request a Return');
    }

    public function test_return_accepted_on_final_inclusive_day(): void
    {
        $buyer = $this->buyer();
        $order = $this->makeOrder($buyer);
        $item = $this->addItem($order);

        $deliveredAt = Carbon::now()->subDays(6)->startOfDay()->setTime(9, 0);
        $this->deliverOrder($order, $deliveredAt);

        // Final day of a 7-day window: delivered day + 7 (inclusive).
        Carbon::setTestNow($deliveredAt->copy()->addDays(7)->setTime(20, 0));

        Mail::fake();

        $this->submitReturn($buyer, $item)->assertRedirect();

        $this->assertDatabaseHas('returns', [
            'order_item_id' => $item->id,
            'status' => 'pending',
        ]);

        Carbon::setTestNow();
    }

    public function test_return_period_expired_rejects_request(): void
    {
        $buyer = $this->buyer();
        $order = $this->makeOrder($buyer);
        $item = $this->addItem($order);

        $deliveredAt = Carbon::now()->subDays(10)->startOfDay()->setTime(9, 0);
        $this->deliverOrder($order, $deliveredAt);

        Carbon::setTestNow($deliveredAt->copy()->addDays(8)); // past the 7-day window

        $this->actingAs($buyer)
            ->get(route('orders.show', ['order' => $order]))
            ->assertOk()
            ->assertDontSee('Return Product')
            ->assertSee('Return period has expired.');

        $this->submitReturn($buyer, $item)
            ->assertRedirect()
            ->assertSessionHasErrors('order_item_id');

        $this->assertDatabaseCount('returns', 0);

        Carbon::setTestNow();
    }

    // ------------------------------------------------------------------
    // TEST 4: not delivered
    // ------------------------------------------------------------------

    public function test_undelivered_order_cannot_be_returned(): void
    {
        $buyer = $this->buyer();
        $order = $this->makeOrder($buyer, 'shipped'); // shipped, no delivery row
        $item = $this->addItem($order);

        $this->actingAs($buyer)
            ->get(route('orders.show', ['order' => $order]))
            ->assertOk()
            ->assertDontSee('Return Product');

        $this->submitReturn($buyer, $item)
            ->assertRedirect()
            ->assertSessionHasErrors('order_item_id');

        $this->assertDatabaseCount('returns', 0);
    }

    // ------------------------------------------------------------------
    // TEST 5: buyer cannot return another buyer's order
    // ------------------------------------------------------------------

    public function test_buyer_cannot_return_or_view_anothers_order_item(): void
    {
        $owner = $this->buyer();
        $intruder = $this->buyer();

        $order = $this->makeOrder($owner);
        $item = $this->addItem($order);
        $this->deliverOrder($order);

        $this->actingAs($intruder)
            ->get(route('returns.create', ['item' => $item->id]))
            ->assertStatus(403);

        $this->submitReturn($intruder, $item)
            ->assertRedirect()
            ->assertSessionHasErrors('order_item_id');

        $this->assertDatabaseCount('returns', 0);
    }

    // ------------------------------------------------------------------
    // TEST 6: duplicate return request
    // ------------------------------------------------------------------

    public function test_duplicate_return_request_is_rejected(): void
    {
        $buyer = $this->buyer();
        $order = $this->makeOrder($buyer);
        $item = $this->addItem($order);
        $this->deliverOrder($order);

        Mail::fake();

        $this->submitReturn($buyer, $item)->assertRedirect();
        $this->assertDatabaseCount('returns', 1);

        // Same item again while the first request is pending -> rejected.
        $this->submitReturn($buyer, $item)
            ->assertRedirect()
            ->assertSessionHasErrors('order_item_id');

        $this->assertDatabaseCount('returns', 1);
    }

    // ------------------------------------------------------------------
    // TEST 7: quantity greater than purchased
    // ------------------------------------------------------------------

    public function test_return_quantity_cannot_exceed_purchased_quantity(): void
    {
        $buyer = $this->buyer();
        $order = $this->makeOrder($buyer);
        $item = $this->addItem($order, null, 100.00, 2);
        $this->deliverOrder($order);

        Mail::fake();

        $this->submitReturn($buyer, $item, ['quantity' => 3])
            ->assertRedirect()
            ->assertSessionHasErrors('order_item_id');

        $this->assertDatabaseCount('returns', 0);
    }

    // ------------------------------------------------------------------
    // TEST 8: successful creation + TEST 13/14 notification & email
    // ------------------------------------------------------------------

    public function test_return_request_created_with_notification_and_email(): void
    {
        $buyer = $this->buyer();
        $seller = $this->seller();
        $order = $this->makeOrder($buyer);
        $item = $this->addItem($order, $seller);
        $this->deliverOrder($order);

        Mail::fake();

        $return = null;
        $this->submitReturn($buyer, $item, [
            'description' => 'Damaged armrest.',
            'quantity' => 1,
        ])->assertRedirect();

        $return = ReturnRequest::query()->where('order_item_id', $item->id)->first();

        $this->assertNotNull($return);
        $this->assertSame('pending', $return->status);
        $this->assertSame(1, $return->quantity);
        $this->assertSame($seller->id, $return->seller_id);
        $this->assertNotNull($return->return_deadline);
        $this->assertSame('Product damaged', $return->reason);

        // Buyer notified in-app (existing StoreAlert system).
        $this->assertTrue(
            $buyer->notifications()->where('data->title', 'Return request submitted')->exists()
        );

        // Seller told about the return on their product.
        $this->assertTrue(
            $seller->notifications()->where('data->title', 'New return request')->exists()
        );

        // Buyer received the confirmation email only.
        Mail::assertSent(ReturnRequestedMail::class, 1);
        Mail::assertSent(ReturnRequestedMail::class, fn ($mail) => $mail->hasTo($buyer->email));

        // The Returns & Refunds page lists the request.
        $this->actingAs($buyer)
            ->get(route('returns.index'))
            ->assertOk()
            ->assertSee($return->return_number);

        $this->actingAs($buyer)
            ->get(route('returns.show', ['return' => $return]))
            ->assertOk()
            ->assertSee('IKEA MARKUS Chair');
    }

    // ------------------------------------------------------------------
    // TEST 9: admin approval
    // ------------------------------------------------------------------

    public function test_admin_can_approve_pending_return(): void
    {
        $buyer = $this->buyer();
        $order = $this->makeOrder($buyer);
        $item = $this->addItem($order);
        $this->deliverOrder($order);

        $return = $this->createReturn($item, $buyer);

        Mail::fake();

        $this->actingAs($this->admin())
            ->post(route('admin.returns.approve', ['return' => $return]))
            ->assertRedirect();

        $return->refresh();

        $this->assertSame('approved', $return->status);
        $this->assertNotNull($return->approved_at);
        $this->assertSame('pending', $return->refund_status);
        $this->assertEquals(100.00, (float) $return->refund_amount);

        Mail::assertSent(ReturnApprovedMail::class, 1);
        $this->assertTrue(
            $buyer->notifications()->where('data->title', 'Return request approved')->exists()
        );
    }

    // ------------------------------------------------------------------
    // TEST 10: admin rejection stores the mandatory reason
    // ------------------------------------------------------------------

    public function test_admin_rejection_requires_and_stores_reason(): void
    {
        $buyer = $this->buyer();
        $order = $this->makeOrder($buyer);
        $item = $this->addItem($order);
        $this->deliverOrder($order);
        $return = $this->createReturn($item, $buyer);

        Mail::fake();

        // Missing reason is rejected.
        $this->actingAs($this->admin())
            ->post(route('admin.returns.reject', ['return' => $return]), ['rejection_reason' => ''])
            ->assertSessionHasErrors('rejection_reason');

        $this->actingAs($this->admin())
            ->post(route('admin.returns.reject', ['return' => $return]), [
                'rejection_reason' => 'Return request rejected because the product is not eligible under the demo return policy.',
            ])
            ->assertRedirect();

        $return->refresh();

        $this->assertSame('rejected', $return->status);
        $this->assertNotNull($return->rejected_at);
        $this->assertStringContainsString('demo return policy', (string) $return->rejection_reason);

        Mail::assertSent(ReturnRejectedMail::class, 1);
        $this->assertTrue(
            $buyer->notifications()->where('data->title', 'Return request rejected')->exists()
        );
    }

    // ------------------------------------------------------------------
    // TEST 11: seller sees only their own products' returns
    // ------------------------------------------------------------------

    public function test_seller_sees_only_their_own_product_returns(): void
    {
        $sellerA = $this->seller();
        $sellerB = $this->seller();
        $buyer = $this->buyer();

        $orderA = $this->makeOrder($buyer);
        $itemA = $this->addItem($orderA, $sellerA, 100.00, 1, 'product-a');
        $this->deliverOrder($orderA);
        $returnA = $this->createReturn($itemA, $buyer);

        $orderB = $this->makeOrder($buyer);
        $itemB = $this->addItem($orderB, $sellerB, 100.00, 1, 'product-b');
        $this->deliverOrder($orderB);
        $returnB = $this->createReturn($itemB, $buyer);

        $this->actingAs($sellerA)
            ->get(route('seller.returns.index'))
            ->assertOk()
            ->assertSee($returnA->return_number)
            ->assertDontSee($returnB->return_number);

        // Seller B cannot open Seller A's return.
        $this->actingAs($sellerB)
            ->get(route('seller.returns.show', ['return' => $returnA]))
            ->assertStatus(403);

        $this->actingAs($sellerA)
            ->get(route('seller.returns.show', ['return' => $returnA]))
            ->assertOk();
    }

    // ------------------------------------------------------------------
    // TEST 12: delivery partner sees only assigned pickups
    // ------------------------------------------------------------------

    public function test_partner_sees_only_their_assigned_pickups(): void
    {
        $partnerA = $this->partner();
        $partnerB = $this->partner();
        $admin = $this->admin();
        $buyer = $this->buyer();

        $order = $this->makeOrder($buyer);
        $item = $this->addItem($order);
        $this->deliverOrder($order);
        $return = $this->createReturn($item, $buyer, 'approved');

        Mail::fake();

        $this->actingAs($admin)
            ->post(route('admin.returns.pickup', ['return' => $return]), [
                'delivery_partner_id' => $partnerA->id,
                'pickup_instructions' => 'Collect before 6 PM.',
            ])
            ->assertRedirect();

        $return->refresh();
        $this->assertSame('pickup_scheduled', $return->status);

        $this->assertTrue(
            $partnerA->notifications()->where('data->title', 'Return pickup assigned')->exists()
        );
        Mail::assertSent(ReturnPickupAssignedMail::class, 1);
        Mail::assertSent(ReturnPickupAssignedMail::class, fn ($mail) => $mail->hasTo($partnerA->email));

        $this->actingAs($partnerA)
            ->get(route('delivery.pickups.index'))
            ->assertOk()
            ->assertSee($return->return_number);

        $this->actingAs($partnerA)
            ->get(route('delivery.pickups.show', ['pickup' => $return]))
            ->assertOk()
            ->assertSee('Collect before 6 PM.');

        $this->actingAs($partnerB)
            ->get(route('delivery.pickups.show', ['pickup' => $return]))
            ->assertStatus(403);

        $this->actingAs($partnerB)
            ->post(route('delivery.pickups.collect', ['pickup' => $return]))
            ->assertStatus(403);

        $this->actingAs($partnerA)
            ->post(route('delivery.pickups.collect', ['pickup' => $return]))
            ->assertRedirect();

        $return->refresh();
        $this->assertSame('received', $return->status);
        $this->assertNotNull($return->received_at);
        $this->assertTrue(
            $buyer->notifications()->where('data->title', 'Product received')->exists()
        );
    }

    // ------------------------------------------------------------------
    // TEST 15: refund uses the historical order-item price
    // ------------------------------------------------------------------

    public function test_refund_uses_historical_order_item_price(): void
    {
        $buyer = $this->buyer();
        $order = $this->makeOrder($buyer, 'delivered', [
            'subtotal' => 378.00,
            'discount_amount' => 37.80,
            'tax' => 0,
            'shipping_cost' => 50.00,
            'total' => 390.20,
        ]);
        $item = $this->addItem($order, null, 189.00, 2);
        $this->deliverOrder($order);

        Mail::fake();

        $this->submitReturn($buyer, $item, ['quantity' => 2])->assertRedirect();

        $return = ReturnRequest::query()->where('order_item_id', $item->id)->first();

        // 189.00 * 2 = 378.00; discount share = 37.80 -> total 340.20.
        $this->assertEquals(340.20, round((float) $return->refund_amount, 2));
        // Policy default: shipping is NOT refunded.
        $this->assertEquals(0.00, (float) $return->shipping_refund_amount);
    }

    // ------------------------------------------------------------------
    // TEST 16: refunded item cannot be returned again
    // ------------------------------------------------------------------

    public function test_refunded_item_cannot_be_returned_again(): void
    {
        $buyer = $this->buyer();
        $order = $this->makeOrder($buyer);
        $item = $this->addItem($order);
        $this->deliverOrder($order);

        $this->createReturn($item, $buyer, 'refunded');

        $this->actingAs($buyer)
            ->get(route('orders.show', ['order' => $order]))
            ->assertOk()
            ->assertDontSee('Return Product')
            ->assertSee('already been refunded');

        $this->submitReturn($buyer, $item)
            ->assertRedirect()
            ->assertSessionHasErrors('order_item_id');
    }

    // ------------------------------------------------------------------
    // TEST 17: full refund workflow + configurable window
    // ------------------------------------------------------------------

    public function test_full_refund_workflow_and_configurable_window(): void
    {
        $admin = $this->admin();
        $buyer = $this->buyer();
        $order = $this->makeOrder($buyer);
        $item = $this->addItem($order);
        $this->deliverOrder($order);

        // Admin raises the window to 10 days via the settings system.
        Setting::put('return_window_days', '10');
        $this->assertSame(10, \App\Support\ReturnPolicy::windowDays());

        Mail::fake();

        $return = $this->createReturn($item, $buyer, 'approved');

        $this->actingAs($admin)
            ->post(route('admin.returns.received', ['return' => $return]))
            ->assertRedirect();

        $return->refresh();
        $this->assertSame('received', $return->status);

        $this->actingAs($admin)
            ->post(route('admin.returns.refund-start', ['return' => $return]), ['refund_reference' => 'REF-77'])
            ->assertRedirect();

        $return->refresh();
        $this->assertSame('refund_processing', $return->status);
        $this->assertSame('processing', $return->refund_status);
        $this->assertTrue(
            $buyer->notifications()->where('data->title', 'Refund processing')->exists()
        );

        $this->actingAs($admin)
            ->post(route('admin.returns.refund-complete', ['return' => $return]), ['refund_reference' => 'REF-77'])
            ->assertRedirect();

        $return->refresh();
        $this->assertSame('refunded', $return->status);
        $this->assertSame('refunded', $return->refund_status);
        $this->assertSame('REF-77', $return->refund_reference);
        $this->assertNotNull($return->refunded_at);

        Mail::assertSent(\App\Mail\RefundProcessedMail::class, 1);
        $this->assertTrue(
            $buyer->notifications()->where('data->title', 'Refund completed')->exists()
        );
    }

    // ------------------------------------------------------------------
    // Role isolation on the return management areas.
    // ------------------------------------------------------------------

    public function test_role_isolation_on_return_management(): void
    {
        // Non-admins cannot reach the admin return management area.
        foreach ([$this->buyer(), $this->seller(), $this->partner()] as $user) {
            $this->actingAs($user)->get('/admin/returns')->assertStatus(403);
        }

        // The buyer area is auth-only; signed-in users may list their OWN
        // returns (a seller/partner simply has none). Ownership of a single
        // return is enforced on the detail page.
        $owner = $this->buyer();
        $order = $this->makeOrder($owner);
        $item = $this->addItem($order);
        $this->deliverOrder($order);
        $return = $this->createReturn($item, $owner);

        $this->actingAs($owner)->get('/returns')->assertOk();

        $this->actingAs($this->buyer())
            ->get(route('returns.show', ['return' => $return]))
            ->assertStatus(403);

        $this->actingAs($this->partner())
            ->get(route('returns.show', ['return' => $return]))
            ->assertStatus(403);

        auth()->guard('web')->logout();

        $this->get('/returns')->assertRedirect(route('login'));

        $this->actingAs($this->admin())
            ->get('/admin/returns')
            ->assertOk();
    }
}
