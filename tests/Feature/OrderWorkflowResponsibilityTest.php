<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Notifications\StoreAlert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class OrderWorkflowResponsibilityTest extends TestCase
{
    use RefreshDatabase;

    private function createUser(string $role): User
    {
        return User::factory()->create(['account_type' => $role]);
    }

    private function createOrderWithSeller(User $buyer, User $seller, string $status = 'confirmed'): Order
    {
        $order = Order::create([
            'user_id'          => $buyer->id,
            'customer_email'   => $buyer->email,
            'order_number'     => 'KDP-' . strtoupper(uniqid()),
            'status'           => $status,
            'subtotal'         => 100,
            'tax'              => 0,
            'shipping_cost'    => 0,
            'total'            => 100,
            'payment_method'   => 'cod',
            'shipping_name'    => $buyer->name,
            'shipping_phone'   => '9999999999',
            'shipping_address' => '1 Main St',
            'shipping_city'    => 'Springfield',
            'shipping_state'   => 'IL',
            'shipping_pincode' => '10001',
        ]);

        OrderItem::create([
            'order_id'      => $order->id,
            'seller_id'     => $seller->id,
            'product_title' => 'Widget',
            'product_slug'  => 'widget-' . uniqid(),
            'sku'           => 'WGT-1',
            'price'         => 100,
            'quantity'      => 1,
            'subtotal'      => 100,
            'total'         => 100,
        ]);

        return $order;
    }

    public function test_admin_login_lands_on_storefront_instead_of_admin_dashboard(): void
    {
        $admin = $this->createUser('admin');

        $this->post('/login', [
            'email'    => $admin->email,
            'password' => 'password',
        ])->assertRedirect('/');

        $this->assertAuthenticatedAs($admin);
    }

    public function test_admin_cannot_move_order_confirmed_to_processing(): void
    {
        $admin  = $this->createUser('admin');
        $buyer  = $this->createUser('buyer');
        $seller = $this->createUser('seller');
        $order  = $this->createOrderWithSeller($buyer, $seller, 'confirmed');

        $response = $this->actingAs($admin)->post("/admin/orders/{$order->id}/status", [
            'status' => 'processing',
        ]);

        $response->assertStatus(403);
        $this->assertEquals('confirmed', $order->fresh()->status);
    }

    public function test_admin_cannot_move_order_processing_to_ready_for_pickup(): void
    {
        $admin  = $this->createUser('admin');
        $buyer  = $this->createUser('buyer');
        $seller = $this->createUser('seller');
        $order  = $this->createOrderWithSeller($buyer, $seller, 'processing');

        $response = $this->actingAs($admin)->post("/admin/orders/{$order->id}/status", [
            'status' => 'ready_for_pickup',
        ]);

        $response->assertStatus(403);
        $this->assertEquals('processing', $order->fresh()->status);
    }

    public function test_seller_can_progress_from_confirmed_to_processing_to_ready_for_pickup(): void
    {
        $buyer  = $this->createUser('buyer');
        $seller = $this->createUser('seller');
        $order  = $this->createOrderWithSeller($buyer, $seller, 'confirmed');

        // Step 1: Seller marks processing
        $this->actingAs($seller)->post("/seller/orders/{$order->id}/status", [
            'status' => 'processing',
        ])->assertRedirect();

        $this->assertEquals('processing', $order->fresh()->status);

        // Step 2: Seller marks ready for pickup
        $this->actingAs($seller)->post("/seller/orders/{$order->id}/status", [
            'status' => 'ready_for_pickup',
        ])->assertRedirect();

        $this->assertEquals('ready_for_pickup', $order->fresh()->status);
    }

    public function test_unrelated_seller_cannot_update_order(): void
    {
        $buyer          = $this->createUser('buyer');
        $sellerOwner    = $this->createUser('seller');
        $otherSeller    = $this->createUser('seller');
        $order          = $this->createOrderWithSeller($buyer, $sellerOwner, 'confirmed');

        $this->actingAs($otherSeller)->post("/seller/orders/{$order->id}/status", [
            'status' => 'processing',
        ])->assertStatus(403);

        $this->assertEquals('confirmed', $order->fresh()->status);
    }

    public function test_marking_ready_for_pickup_notifies_administrators(): void
    {
        Notification::fake();

        $admin  = $this->createUser('admin');
        $buyer  = $this->createUser('buyer');
        $seller = $this->createUser('seller');
        $order  = $this->createOrderWithSeller($buyer, $seller, 'processing');

        $this->actingAs($seller)->post("/seller/orders/{$order->id}/status", [
            'status' => 'ready_for_pickup',
        ])->assertRedirect();

        Notification::assertSentTo(
            $admin,
            StoreAlert::class,
            function (StoreAlert $notification) use ($order) {
                return $notification->title === 'Order Ready for Pickup'
                    && ($notification->meta['order_id'] ?? null) === $order->id;
            }
        );
    }

    // ------------------------------------------------------------------
    // MULTI-SELLER PER-SELLER READINESS
    // ------------------------------------------------------------------

    private function addSellerLine(Order $order, User $seller): OrderItem
    {
        return OrderItem::create([
            'order_id'      => $order->id,
            'seller_id'     => $seller->id,
            'product_title' => 'Gadget ' . uniqid(),
            'product_slug'  => 'gadget-' . uniqid(),
            'sku'           => 'GDT-' . substr(uniqid(), -6),
            'price'         => 50,
            'quantity'      => 1,
            'subtotal'      => 50,
            'total'         => 50,
        ]);
    }

    public function test_first_seller_marking_ready_leaves_order_partially_ready(): void
    {
        $buyer   = $this->createUser('buyer');
        $sellerA = $this->createUser('seller');
        $sellerB = $this->createUser('seller');
        $order   = $this->createOrderWithSeller($buyer, $sellerA, 'processing');
        $this->addSellerLine($order, $sellerB);

        // Seller A packs their portion.
        $this->actingAs($sellerA)->post("/seller/orders/{$order->id}/status", [
            'status' => 'ready_for_pickup',
        ])->assertRedirect();

        // The shared order must NOT advance yet, but A's lines are stamped.
        $this->assertSame('processing', $order->fresh()->status);
        $this->assertTrue(
            $order->items()->where('seller_id', $sellerA->id)->whereNotNull('seller_ready_at')->exists()
        );
        $this->assertFalse(
            $order->items()->where('seller_id', $sellerB->id)->whereNotNull('seller_ready_at')->exists()
        );
    }

    public function test_last_seller_marking_ready_advances_shared_order(): void
    {
        $buyer   = $this->createUser('buyer');
        $sellerA = $this->createUser('seller');
        $sellerB = $this->createUser('seller');
        $order   = $this->createOrderWithSeller($buyer, $sellerA, 'processing');
        $this->addSellerLine($order, $sellerB);

        $this->actingAs($sellerA)->post("/seller/orders/{$order->id}/status", [
            'status' => 'ready_for_pickup',
        ])->assertRedirect();
        $this->assertSame('processing', $order->fresh()->status);

        // Seller B finishes packing: now (and only now) the order advances.
        $this->actingAs($sellerB)->post("/seller/orders/{$order->id}/status", [
            'status' => 'ready_for_pickup',
        ])->assertRedirect();

        $this->assertSame('ready_for_pickup', $order->fresh()->status);
        $this->assertTrue($order->fresh()->allSellersReady());
    }

    public function test_order_status_service_blocks_ready_until_all_sellers_pack(): void
    {
        $buyer   = $this->createUser('buyer');
        $sellerA = $this->createUser('seller');
        $sellerB = $this->createUser('seller');
        $order   = $this->createOrderWithSeller($buyer, $sellerA, 'processing');
        $this->addSellerLine($order, $sellerB);

        // Stamp only seller A's lines directly (simulating a partial pack).
        $order->items()->where('seller_id', $sellerA->id)->update(['seller_ready_at' => now()]);

        $this->assertFalse($order->fresh()->allSellersReady());
        $this->assertTrue($order->fresh()->isPartiallyReady());

        // The service itself must refuse the transition (backstop guard).
        $this->expectException(\Illuminate\Validation\ValidationException::class);

        app(\App\Services\OrderStatusService::class)
            ->transition($order->fresh(), 'ready_for_pickup', $sellerA);
    }

    public function test_seller_sees_partially_ready_state_on_order_page(): void
    {
        $buyer   = $this->createUser('buyer');
        $sellerA = $this->createUser('seller');
        $sellerB = $this->createUser('seller');
        $order   = $this->createOrderWithSeller($buyer, $sellerA, 'processing');
        $this->addSellerLine($order, $sellerB);

        $this->actingAs($sellerA)->post("/seller/orders/{$order->id}/status", [
            'status' => 'ready_for_pickup',
        ])->assertRedirect();

        // Seller A's order page now shows the "your items are ready" banner
        // and no Mark Ready button.
        $this->actingAs($sellerA)->get("/seller/orders/{$order->id}")
            ->assertOk()
            ->assertSee('ready for pickup')
            ->assertDontSee('Mark Ready for Pickup');

        // Seller B still sees the button and the multi-seller notice.
        $this->actingAs($sellerB)->get("/seller/orders/{$order->id}")
            ->assertOk()
            ->assertSee('Mark Ready for Pickup')
            ->assertSee('multi-seller order');
    }

    public function test_admin_order_page_shows_per_seller_readiness(): void
    {
        $admin   = $this->createUser('admin');
        $buyer   = $this->createUser('buyer');
        $sellerA = $this->createUser('seller');
        $sellerB = $this->createUser('seller');
        $order   = $this->createOrderWithSeller($buyer, $sellerA, 'processing');
        $this->addSellerLine($order, $sellerB);

        $this->actingAs($sellerA)->post("/seller/orders/{$order->id}/status", [
            'status' => 'ready_for_pickup',
        ])->assertRedirect();

        $this->actingAs($admin)->get("/admin/orders/{$order->id}")
            ->assertOk()
            ->assertSee('Seller Readiness')
            ->assertSee('Preparing')
            ->assertSee('Partially ready');
    }

    public function test_admin_orders_index_shows_partially_ready_label(): void
    {
        $admin   = $this->createUser('admin');
        $buyer   = $this->createUser('buyer');
        $sellerA = $this->createUser('seller');
        $sellerB = $this->createUser('seller');
        $order   = $this->createOrderWithSeller($buyer, $sellerA, 'processing');
        $this->addSellerLine($order, $sellerB);

        $this->actingAs($sellerA)->post("/seller/orders/{$order->id}/status", [
            'status' => 'ready_for_pickup',
        ])->assertRedirect();

        $this->actingAs($admin)->get('/admin/orders')
            ->assertOk()
            ->assertSee('Partially Ready (1/2 sellers)');
    }
}
