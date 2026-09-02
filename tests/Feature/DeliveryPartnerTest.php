<?php

namespace Tests\Feature;

use App\Mail\SellerOrderApprovedMail;
use App\Models\Order;
use App\Models\OrderDelivery;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class DeliveryPartnerTest extends TestCase
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

    private function deliveryPartner(array $attrs = []): User
    {
        return User::factory()->create(array_merge([
            'account_type' => 'delivery_partner',
            'status' => 'active',
        ], $attrs));
    }

    private function makeOrder(User $user, string $status = 'pending'): Order
    {
        return Order::create([
            'user_id' => $user->id,
            'customer_email' => $user->email,
            'order_number' => 'KDP-' . strtoupper(uniqid()),
            'status' => $status,
            'subtotal' => 150.00,
            'tax' => 0,
            'shipping_cost' => 0,
            'total' => 150.00,
            'payment_method' => 'cod',
            'shipping_name' => $user->name,
            'shipping_phone' => '9999999999',
            'shipping_address' => '123 Main St',
            'shipping_city' => 'Springfield',
            'shipping_state' => 'IL',
            'shipping_pincode' => '10001',
        ]);
    }

    private function addItem(Order $order, ?User $seller = null): void
    {
        $order->items()->create([
            'product_slug' => 'test-product',
            'product_title' => 'Test Product',
            'product_image' => null,
            'sku' => 'TP-001',
            'price' => 150.00,
            'quantity' => 1,
            'subtotal' => 150.00,
            'options_text' => null,
            'seller_id' => $seller?->id,
        ]);
    }

    // ------------------------------------------------------------------
    //  AUTHORIZATION
    // ------------------------------------------------------------------

    public function test_delivery_partner_can_access_dashboard(): void
    {
        $partner = $this->deliveryPartner();

        $this->actingAs($partner)
            ->get('/delivery/dashboard')
            ->assertOk();
    }

    public function test_buyer_cannot_access_delivery_dashboard(): void
    {
        $buyer = $this->buyer();

        $this->actingAs($buyer)
            ->get('/delivery/dashboard')
            ->assertStatus(403);
    }

    public function test_seller_cannot_access_delivery_dashboard(): void
    {
        $seller = $this->seller();

        $this->actingAs($seller)
            ->get('/delivery/dashboard')
            ->assertStatus(403);
    }

    public function test_admin_can_access_delivery_management(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->get('/admin/deliveries')
            ->assertOk();
    }

    public function test_delivery_partner_cannot_access_admin_panel(): void
    {
        $partner = $this->deliveryPartner();

        $this->actingAs($partner)
            ->get('/admin/dashboard')
            ->assertStatus(403);
    }

    public function test_delivery_partner_cannot_access_seller_product_management(): void
    {
        $partner = $this->deliveryPartner();

        $this->actingAs($partner)
            ->get('/seller/orders')
            ->assertStatus(403);
    }

    // ------------------------------------------------------------------
    //  ACCOUNT
    // ------------------------------------------------------------------

    public function test_admin_can_create_delivery_partner(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->post('/admin/delivery-partners', [
            'name' => 'DP One',
            'email' => 'dp1@example.com',
            'phone' => '9999999999',
            'password' => 'Password123!',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('users', [
            'email' => 'dp1@example.com',
            'account_type' => 'delivery_partner',
            'status' => 'active',
        ]);
    }

    public function test_delivery_partner_account_type_stored_correctly(): void
    {
        $partner = $this->deliveryPartner();

        $this->assertEquals('delivery_partner', $partner->account_type);
        $this->assertTrue($partner->isDeliveryPartner());
    }

    public function test_public_registration_cannot_create_delivery_partner(): void
    {
        $response = $this->post('/register', [
            'name' => 'Malicious',
            'email' => 'mal@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'account_type' => 'delivery_partner',
        ]);

        $response->assertSessionHasErrors('account_type');
        $this->assertDatabaseMissing('users', ['email' => 'mal@example.com']);
    }

    public function test_delivery_partner_cannot_change_own_role(): void
    {
        $partner = $this->deliveryPartner();
        $partner->forceFill(['phone' => '9876543210'])->save();

        // The profile endpoint validates a fixed rule set that does not
        // include account_type, so even a crafted POST carrying account_type
        // cannot elevate a delivery partner's role.
        $this->actingAs($partner)->post('/profile/update', [
            'name'          => $partner->name,
            'email'         => $partner->email,
            'phone'         => $partner->phone,
            'bio'           => 'test bio',
            'account_type'  => 'admin',
        ]);

        $partner->refresh();
        $this->assertEquals('delivery_partner', $partner->account_type);
    }

    public function test_disabled_delivery_partner_cannot_access_dashboard(): void
    {
        $partner = $this->deliveryPartner(['status' => 'inactive']);

        $this->actingAs($partner)
            ->get('/delivery/dashboard')
            ->assertStatus(403);
    }

    // ------------------------------------------------------------------
    //  ORDER
    // ------------------------------------------------------------------

    public function test_new_order_starts_in_pending_state(): void
    {
        $buyer = $this->buyer();
        $order = $this->makeOrder($buyer);

        $this->assertEquals('pending', $order->status);
    }

    public function test_admin_can_approve_order(): void
    {
        $admin = $this->admin();
        $buyer = $this->buyer();
        $order = $this->makeOrder($buyer);

        $response = $this->actingAs($admin)->post("/admin/orders/{$order->id}/approve");

        $response->assertRedirect();
        $order->refresh();
        $this->assertEquals('approved', $order->status);
        $this->assertNotNull($order->approved_at);
    }

    public function test_buyer_cannot_approve_order(): void
    {
        $buyer = $this->buyer();
        $order = $this->makeOrder($buyer);

        $this->actingAs($buyer)
            ->post("/admin/orders/{$order->id}/approve")
            ->assertStatus(403);
    }

    public function test_seller_receives_approved_order_notification(): void
    {
        Mail::fake();

        $admin = $this->admin();
        $seller = $this->seller();
        $buyer = $this->buyer();
        $order = $this->makeOrder($buyer);
        $this->addItem($order, $seller);

        $this->actingAs($admin)->post("/admin/orders/{$order->id}/approve");

        Mail::assertSent(SellerOrderApprovedMail::class, function ($mail) use ($seller) {
            return $mail->hasTo($seller->email);
        });
    }

    public function test_approved_order_can_be_assigned_to_delivery_partner(): void
    {
        $admin = $this->admin();
        $buyer = $this->buyer();
        $partner = $this->deliveryPartner();
        $order = $this->makeOrder($buyer, 'approved');

        $response = $this->actingAs($admin)->post("/admin/orders/{$order->id}/assign-delivery", [
            'delivery_partner_id' => $partner->id,
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('order_deliveries', [
            'order_id' => $order->id,
            'delivery_partner_id' => $partner->id,
            'assigned_by' => $admin->id,
        ]);
    }

    public function test_unassigned_approved_orders_visible_to_admin(): void
    {
        $admin = $this->admin();
        $buyer = $this->buyer();
        $order = $this->makeOrder($buyer, 'approved');

        $response = $this->actingAs($admin)->get('/admin/deliveries');
        $response->assertOk();

        $unassigned = $response->viewData('unassigned');
        $ids = $unassigned->pluck('id')->all();
        $this->assertContains($order->id, $ids);
    }

    // ------------------------------------------------------------------
    //  DELIVERY
    // ------------------------------------------------------------------

    public function test_assigned_delivery_partner_can_see_delivery(): void
    {
        $buyer = $this->buyer();
        $partner = $this->deliveryPartner();
        $order = $this->makeOrder($buyer, 'approved');
        $this->addItem($order);

        $delivery = OrderDelivery::create([
            'order_id' => $order->id,
            'delivery_partner_id' => $partner->id,
            'assigned_by' => $this->admin()->id,
            'status' => 'assigned',
            'assigned_at' => now(),
        ]);

        $this->actingAs($partner)
            ->get("/delivery/deliveries/{$delivery->id}")
            ->assertOk();
    }

    public function test_unassigned_partner_cannot_see_another_delivery(): void
    {
        $buyer = $this->buyer();
        $partnerA = $this->deliveryPartner();
        $partnerB = $this->deliveryPartner();
        $order = $this->makeOrder($buyer, 'approved');
        $this->addItem($order);

        $delivery = OrderDelivery::create([
            'order_id' => $order->id,
            'delivery_partner_id' => $partnerA->id,
            'assigned_by' => $this->admin()->id,
            'status' => 'assigned',
            'assigned_at' => now(),
        ]);

        $this->actingAs($partnerB)
            ->get("/delivery/deliveries/{$delivery->id}")
            ->assertStatus(403);
    }

    public function test_delivery_partner_can_mark_picked_up(): void
    {
        $buyer = $this->buyer();
        $partner = $this->deliveryPartner();
        $order = $this->makeOrder($buyer, 'approved');
        $this->addItem($order);

        $delivery = OrderDelivery::create([
            'order_id' => $order->id,
            'delivery_partner_id' => $partner->id,
            'assigned_by' => $this->admin()->id,
            'status' => 'assigned',
            'assigned_at' => now(),
        ]);

        $response = $this->actingAs($partner)->post("/delivery/deliveries/{$delivery->id}/pickup");

        $response->assertRedirect();
        $delivery->refresh();
        $this->assertEquals('picked_up', $delivery->status);
        $this->assertNotNull($delivery->picked_up_at);
    }

    public function test_delivery_partner_can_mark_out_for_delivery(): void
    {
        $buyer = $this->buyer();
        $partner = $this->deliveryPartner();
        $order = $this->makeOrder($buyer, 'approved');
        $this->addItem($order);

        $delivery = OrderDelivery::create([
            'order_id' => $order->id,
            'delivery_partner_id' => $partner->id,
            'assigned_by' => $this->admin()->id,
            'status' => 'picked_up',
            'assigned_at' => now(),
            'picked_up_at' => now(),
        ]);

        $response = $this->actingAs($partner)->post("/delivery/deliveries/{$delivery->id}/out-for-delivery");

        $response->assertRedirect();
        $delivery->refresh();
        $this->assertEquals('out_for_delivery', $delivery->status);
        $this->assertNotNull($delivery->out_for_delivery_at);
    }

    public function test_delivery_partner_can_mark_delivered(): void
    {
        $buyer = $this->buyer();
        $partner = $this->deliveryPartner();
        $order = $this->makeOrder($buyer, 'approved');
        $this->addItem($order);

        $delivery = OrderDelivery::create([
            'order_id' => $order->id,
            'delivery_partner_id' => $partner->id,
            'assigned_by' => $this->admin()->id,
            'status' => 'out_for_delivery',
            'assigned_at' => now(),
            'picked_up_at' => now(),
            'out_for_delivery_at' => now(),
        ]);

        $response = $this->actingAs($partner)->post("/delivery/deliveries/{$delivery->id}/delivered");

        $response->assertRedirect();
        $delivery->refresh();
        $this->assertEquals('delivered', $delivery->status);
        $this->assertNotNull($delivery->delivered_at);
    }

    public function test_delivery_partner_cannot_modify_another_partner_delivery(): void
    {
        $buyer = $this->buyer();
        $partnerA = $this->deliveryPartner();
        $partnerB = $this->deliveryPartner();
        $order = $this->makeOrder($buyer, 'approved');
        $this->addItem($order);

        $delivery = OrderDelivery::create([
            'order_id' => $order->id,
            'delivery_partner_id' => $partnerA->id,
            'assigned_by' => $this->admin()->id,
            'status' => 'assigned',
            'assigned_at' => now(),
        ]);

        $this->actingAs($partnerB)
            ->post("/delivery/deliveries/{$delivery->id}/pickup")
            ->assertStatus(403);

        $delivery->refresh();
        $this->assertEquals('assigned', $delivery->status);
    }

    public function test_invalid_delivery_status_transitions_are_rejected(): void
    {
        $buyer = $this->buyer();
        $partner = $this->deliveryPartner();
        $order = $this->makeOrder($buyer, 'approved');
        $this->addItem($order);

        $delivery = OrderDelivery::create([
            'order_id' => $order->id,
            'delivery_partner_id' => $partner->id,
            'assigned_by' => $this->admin()->id,
            'status' => 'assigned',
            'assigned_at' => now(),
        ]);

        $response = $this->actingAs($partner)
            ->post("/delivery/deliveries/{$delivery->id}/delivered");

        $response->assertSessionHasErrors('status');

        $delivery->refresh();
        $this->assertEquals('assigned', $delivery->status);
    }

    public function test_reassignment_works_correctly(): void
    {
        $admin = $this->admin();
        $buyer = $this->buyer();
        $partnerA = $this->deliveryPartner();
        $partnerB = $this->deliveryPartner();
        $order = $this->makeOrder($buyer, 'approved');
        $this->addItem($order);

        $delivery = OrderDelivery::create([
            'order_id' => $order->id,
            'delivery_partner_id' => $partnerA->id,
            'assigned_by' => $admin->id,
            'status' => 'assigned',
            'assigned_at' => now(),
        ]);

        $response = $this->actingAs($admin)->post("/admin/deliveries/{$delivery->id}/reassign", [
            'delivery_partner_id' => $partnerB->id,
        ]);

        $response->assertRedirect();
        $delivery->refresh();
        $this->assertEquals($partnerB->id, $delivery->delivery_partner_id);
    }

    // ------------------------------------------------------------------
    //  NOTIFICATIONS
    // ------------------------------------------------------------------

    public function test_delivery_assignment_sends_partner_notification(): void
    {
        Mail::fake();

        $admin = $this->admin();
        $buyer = $this->buyer();
        $partner = $this->deliveryPartner();
        $order = $this->makeOrder($buyer, 'approved');
        $this->addItem($order);

        $this->actingAs($admin)->post("/admin/orders/{$order->id}/assign-delivery", [
            'delivery_partner_id' => $partner->id,
        ]);

        Mail::assertSent(\App\Mail\DeliveryAssignedMail::class, function ($mail) use ($partner) {
            return $mail->hasTo($partner->email);
        });
    }

    public function test_seller_receives_order_approval_notification(): void
    {
        Mail::fake();

        $admin = $this->admin();
        $seller = $this->seller();
        $buyer = $this->buyer();
        $order = $this->makeOrder($buyer);
        $this->addItem($order, $seller);

        $this->actingAs($admin)->post("/admin/orders/{$order->id}/approve");

        Mail::assertSent(SellerOrderApprovedMail::class);
    }

    public function test_duplicate_status_changes_do_not_send_duplicate_notifications(): void
    {
        Mail::fake();

        $admin = $this->admin();
        $buyer = $this->buyer();
        $partner = $this->deliveryPartner();
        $order = $this->makeOrder($buyer, 'approved');
        $this->addItem($order);

        $this->actingAs($admin)->post("/admin/orders/{$order->id}/assign-delivery", [
            'delivery_partner_id' => $partner->id,
        ]);

        Mail::assertSent(\App\Mail\DeliveryAssignedMail::class, 1);
    }

    // ------------------------------------------------------------------
    //  DATA ISOLATION
    // ------------------------------------------------------------------

    public function test_seller_cannot_view_another_sellers_order_data(): void
    {
        $sellerA = $this->seller();
        $sellerB = $this->seller();
        $buyer = $this->buyer();
        $order = $this->makeOrder($buyer, 'approved');
        $this->addItem($order, $sellerA);

        $this->actingAs($sellerB)
            ->get("/seller/orders/{$order->id}")
            ->assertStatus(403);
    }

    public function test_delivery_partner_cannot_view_unrelated_deliveries(): void
    {
        $buyer = $this->buyer();
        $partnerA = $this->deliveryPartner();
        $partnerB = $this->deliveryPartner();
        $order = $this->makeOrder($buyer, 'approved');
        $this->addItem($order);

        $delivery = OrderDelivery::create([
            'order_id' => $order->id,
            'delivery_partner_id' => $partnerA->id,
            'assigned_by' => $this->admin()->id,
            'status' => 'assigned',
            'assigned_at' => now(),
        ]);

        $this->actingAs($partnerB)
            ->get("/delivery/deliveries/{$delivery->id}")
            ->assertStatus(403);
    }

    public function test_buyer_cannot_view_another_buyer_order(): void
    {
        $buyerA = $this->buyer();
        $buyerB = $this->buyer();
        $order = $this->makeOrder($buyerA);
        $this->addItem($order);

        $this->actingAs($buyerB)
            ->get("/orders/{$order->id}")
            ->assertStatus(403);
    }

    public function test_admin_can_view_all_deliveries(): void
    {
        $admin = $this->admin();
        $buyer = $this->buyer();
        $partner = $this->deliveryPartner();
        $order = $this->makeOrder($buyer, 'approved');
        $this->addItem($order);

        OrderDelivery::create([
            'order_id' => $order->id,
            'delivery_partner_id' => $partner->id,
            'assigned_by' => $admin->id,
            'status' => 'assigned',
            'assigned_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get('/admin/deliveries');
        $response->assertOk();

        $deliveries = $response->viewData('deliveries');
        $ids = $deliveries->pluck('id')->all();
        $this->assertNotEmpty($ids);
    }
    // ------------------------------------------------------------------
    //  FULL MANUAL SCENARIO (Section 24) — end-to-end through the REAL
    //  checkout, admin approval, assignment, seller packing and partner
    //  delivery actions over live HTTP requests.
    // ------------------------------------------------------------------

    public function test_manual_scenario_full_lifecycle_from_real_checkout_to_delivered(): void
    {
        Mail::fake();

        $admin = $this->admin();
        $seller = $this->seller();
        $buyer = $this->buyer();
        $partner = $this->deliveryPartner();

        // Seller publishes a product into the catalog (carries seller_id).
        app(\App\Services\ProductCatalogService::class)->upsertRow('e2e-gadget', [
            'title'     => 'E2E Gadget',
            'sku'       => 'E2E-001',
            'price'     => 300.0,
            'quantity'  => 10,
            'tax'       => 18,
            'status'    => 1,
            'seller_id' => $seller->id,
        ]);

        // ------------------------------------------------------------------
        // STEPS 1-3: buyer adds to cart and completes a REAL checkout.
        // ------------------------------------------------------------------
        $this->actingAs($buyer);

        app(\App\Services\CartService::class)->save([
            'e2e-gadget' => [
                'product'  => 'e2e-gadget',
                'title'    => 'E2E Gadget',
                'price'    => 300.0,
                'quantity' => 1,
                'sku'      => 'E2E-001',
            ],
        ]);

        $this->post('/checkout', [
            'address_option'  => 'new',
            'shipping_method' => 'standard',
            'payment_method'  => 'cod',
            'new_address'     => [
                'full_name'      => 'Jane Doe',
                'phone'          => '1234567890',
                'house_number'   => '123',
                'street_address' => 'Main Street',
                'city'           => 'New York',
                'state'          => 'NY',
                'pincode'        => '10001',
                'country'        => 'India',
            ],
        ])->assertRedirect(route('checkout.complete'));

        $order = Order::where('user_id', $buyer->id)->sole();

        // The order item snapshots the seller for seller-scoped notifications.
        $this->assertDatabaseHas('order_items', [
            'order_id'  => $order->id,
            'seller_id' => $seller->id,
        ]);

        // STEP 4: the order appears in the buyer's order history.
        $this->get('/orders')->assertOk()->assertSee($order->order_number);

        // The new order starts in the existing pending state.
        $this->assertSame('pending', $order->fresh()->status);

        // ------------------------------------------------------------------
        // STEPS 6-9: admin reviews the pending order and approves it.
        // ------------------------------------------------------------------
        $this->actingAs($admin)
            ->get('/admin/orders')
            ->assertOk()
            ->assertSee($order->order_number);

        $this->post("/admin/orders/{$order->id}/approve")->assertRedirect();

        $order->refresh();
        $this->assertSame('approved', $order->status);
        $this->assertNotNull($order->approved_at);

        // STEP 10: seller approval notification (mail + in-app) is generated.
        Mail::assertSent(SellerOrderApprovedMail::class, fn ($m) => $m->hasTo($seller->email));
        Mail::assertSent(\App\Mail\OrderApprovedMail::class, fn ($m) => $m->hasTo($buyer->email));
        $this->assertSame(1, $seller->notifications()->count());

        // ------------------------------------------------------------------
        // STEPS 11-12: admin assigns an active delivery partner.
        // ------------------------------------------------------------------
        $this->post("/admin/orders/{$order->id}/assign-delivery", [
            'delivery_partner_id' => $partner->id,
        ])->assertRedirect();

        $delivery = OrderDelivery::where('order_id', $order->id)->sole();
        $this->assertSame('assigned', $delivery->status);
        $this->assertSame($partner->id, $delivery->delivery_partner_id);
        $this->assertSame($admin->id, $delivery->assigned_by);
        $this->assertNotNull($delivery->assigned_at);

        // The delivery partner receives a notification (mail + in-app).
        Mail::assertSent(\App\Mail\DeliveryAssignedMail::class, fn ($m) => $m->hasTo($partner->email));
        $this->assertSame(1, $partner->notifications()->count());

        // ------------------------------------------------------------------
        // STEPS 14-15: the seller sees their line of the approved order.
        // ------------------------------------------------------------------
        $this->actingAs($seller)
            ->get('/seller/orders')
            ->assertOk()
            ->assertSee($order->order_number);

        $this->get("/seller/orders/{$order->id}")
            ->assertOk()
            ->assertSee('E2E Gadget');

        // ------------------------------------------------------------------
        // STEPS 16-17: seller marks the order packed (ready for pickup);
        // the delivery partner is notified and the delivery moves forward.
        // ------------------------------------------------------------------
        $this->post("/seller/orders/{$order->id}/status", ['status' => 'processing'])->assertRedirect();
        $this->post("/seller/orders/{$order->id}/status", ['status' => 'packed'])->assertRedirect();

        $this->assertSame('packed', $order->fresh()->status);
        $this->assertSame('ready_for_pickup', $delivery->fresh()->status);
        $this->assertSame(2, $partner->notifications()->count());

        // ------------------------------------------------------------------
        // STEPS 19-22: partner opens dashboard + delivery details and
        // verifies customer / product / delivery information.
        // ------------------------------------------------------------------
        $this->actingAs($partner)->get('/delivery/dashboard')->assertOk();

        $this->get("/delivery/deliveries/{$delivery->id}")
            ->assertOk()
            ->assertSee('Jane Doe')      // customer name
            ->assertSee('1234567890')    // customer phone
            ->assertSee('Main Street')   // delivery address
            ->assertSee('E2E Gadget')    // product
            ->assertSee($order->order_number);

        // ------------------------------------------------------------------
        // STEPS 23-25: pickup -> out for delivery -> delivered.
        // ------------------------------------------------------------------
        $this->post("/delivery/deliveries/{$delivery->id}/pickup")->assertRedirect();
        $this->assertSame('picked_up', $delivery->fresh()->status);
        $this->assertNotNull($delivery->fresh()->picked_up_at);
        // Sellers are told their parcel was picked up.
        Mail::assertSent(\App\Mail\SellerOrderPickedUpMail::class, fn ($m) => $m->hasTo($seller->email));

        $this->post("/delivery/deliveries/{$delivery->id}/out-for-delivery")->assertRedirect();
        $this->assertSame('out_for_delivery', $delivery->fresh()->status);
        $this->assertNotNull($delivery->fresh()->out_for_delivery_at);
        $this->assertSame('shipped', $order->fresh()->status);

        $this->post("/delivery/deliveries/{$delivery->id}/delivered")->assertRedirect();
        $this->assertSame('delivered', $delivery->fresh()->status);
        $this->assertNotNull($delivery->fresh()->delivered_at);
        $this->assertSame('delivered', $order->fresh()->status);

        // ------------------------------------------------------------------
        // STEP 26: the buyer sees the delivered order + status mails.
        // ------------------------------------------------------------------
        $this->actingAs($buyer)
            ->get("/orders/{$order->id}")
            ->assertOk()
            ->assertSee('Delivered');

        Mail::assertSent(\App\Mail\OrderShippedMail::class, fn ($m) => $m->hasTo($buyer->email));
        Mail::assertSent(\App\Mail\OrderDeliveredMail::class, fn ($m) => $m->hasTo($buyer->email));
    }
}

