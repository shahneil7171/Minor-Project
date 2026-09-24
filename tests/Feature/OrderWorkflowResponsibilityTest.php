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
}
