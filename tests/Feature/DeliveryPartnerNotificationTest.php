<?php

use App\Mail\DeliveryAssignedMail;
use App\Models\Order;
use App\Models\OrderDelivery;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/*
|--------------------------------------------------------------------------
| Delivery Partner Order Notifications
|--------------------------------------------------------------------------
| Covers the assignment workflow: an admin assigns / reassigns an approved
| order to a delivery partner, which creates exactly one in-app notification
| and one email for that partner — never for the buyer, seller or admin,
| never twice for an unchanged assignment, and never for a partner without
| an email address.
*/

function dpnAdmin(): User
{
    return User::factory()->create(['account_type' => 'admin', 'status' => 'active']);
}

function dpnBuyer(): User
{
    return User::factory()->create([
        'account_type' => 'buyer',
        'status' => 'active',
        'name' => 'Tobey Spider',
        'phone' => '9876543210',
    ]);
}

function dpnSeller(): User
{
    return User::factory()->create([
        'account_type' => 'seller',
        'status' => 'active',
        'name' => 'Sadie Sink',
    ]);
}

function dpnPartner(array $overrides = []): User
{
    return User::factory()->create(array_merge([
        'account_type' => 'delivery_partner',
        'status' => 'active',
        'name' => 'Dan Partner',
    ], $overrides));
}

function dpnOrder(User $buyer, string $status = 'approved'): Order
{
    return Order::create([
        'user_id' => $buyer->id,
        'customer_email' => $buyer->email,
        'order_number' => 'ORD-' . strtoupper(uniqid()),
        'status' => $status,
        'approved_at' => now(),
        'subtotal' => 273.02,
        'tax' => 0,
        'shipping_cost' => 0,
        'total' => 273.02,
        'payment_method' => 'cod',
        'shipping_name' => 'Tobey Spider',
        'shipping_phone' => '9876543210',
        'shipping_address' => '908, Vivanta Complex, Shantadevi Road',
        'shipping_city' => 'Navsari',
        'shipping_state' => 'Gujarat',
        'shipping_pincode' => '396445',
        'shipping_country' => 'India',
        'notes' => 'Please call before arriving.',
    ]);
}

function dpnItem(Order $order, ?User $seller = null): void
{
    $order->items()->create([
        'product_slug' => 'ikea-markus-chair',
        'product_title' => 'IKEA MARKUS Ergonomic Office Chair',
        'product_image' => null,
        'sku' => 'CH-001',
        'price' => 273.02,
        'quantity' => 1,
        'subtotal' => 273.02,
        'options_text' => null,
        'seller_id' => $seller?->id,
    ]);
}

/** Assign the order through the real admin endpoint. */
function dpnAssign($case, Order $order, User $partner, User $admin)
{
    return $case->actingAs($admin)->post("/admin/orders/{$order->id}/assign-delivery", [
        'delivery_partner_id' => $partner->id,
    ]);
}

/** Build an existing delivery row without going through the admin endpoint. */
function dpnDelivery(Order $order, User $partner, User $admin, string $status = 'assigned'): OrderDelivery
{
    return OrderDelivery::create([
        'order_id' => $order->id,
        'delivery_partner_id' => $partner->id,
        'assigned_by' => $admin->id,
        'status' => $status,
        'assigned_at' => now(),
    ]);
}

// ---------------------------------------------------------------------
//  TEST 1 — Admin assigns an approved order to Delivery Partner A
// ---------------------------------------------------------------------

test('TEST 1: assigning an approved order saves the assignment, notifies the partner and emails them', function () {
    Mail::fake();

    $admin = dpnAdmin();
    $buyer = dpnBuyer();
    $partner = dpnPartner();
    $order = dpnOrder($buyer);
    dpnItem($order);

    dpnAssign($this, $order, $partner, $admin)->assertRedirect()->assertSessionHasNoErrors();

    // Assignment persisted.
    $delivery = OrderDelivery::where('order_id', $order->id)->sole();
    expect((int) $delivery->delivery_partner_id)->toBe($partner->id);
    expect((int) $delivery->assigned_by)->toBe($admin->id);
    expect($delivery->status)->toBe('assigned');

    // In-app notification created for the delivery partner only.
    expect($partner->notifications()->count())->toBe(1);
    expect($buyer->notifications()->count())->toBe(0);
    expect($admin->notifications()->count())->toBe(0);

    // Email sent to the delivery partner.
    Mail::assertSent(DeliveryAssignedMail::class, 1);
    Mail::assertSent(DeliveryAssignedMail::class, fn (DeliveryAssignedMail $mail) => $mail->hasTo($partner->email));
});

// ---------------------------------------------------------------------
//  TEST 2 — Admin refreshes / views the order without changing anything
// ---------------------------------------------------------------------

test('TEST 2: re-saving the same assignment sends no duplicate notification or email', function () {
    Mail::fake();

    $admin = dpnAdmin();
    $buyer = dpnBuyer();
    $partner = dpnPartner();
    $order = dpnOrder($buyer);
    dpnItem($order);

    dpnAssign($this, $order, $partner, $admin)->assertRedirect();
    $delivery = OrderDelivery::where('order_id', $order->id)->sole();

    expect($partner->notifications()->count())->toBe(1);
    Mail::assertSent(DeliveryAssignedMail::class, 1);

    // Re-submitting the exact same partner (page refresh / second click)…
    dpnAssign($this, $order, $partner, $admin)->assertRedirect();

    // …viewing the assignment page…
    $this->actingAs($admin)->get("/admin/deliveries/{$delivery->id}")->assertOk();

    // …and re-saving through the reassign endpoint with the same partner.
    $this->actingAs($admin)
        ->post("/admin/deliveries/{$delivery->id}/reassign", ['delivery_partner_id' => $partner->id])
        ->assertRedirect();

    // Nothing was duplicated anywhere.
    expect($partner->notifications()->count())->toBe(1);
    Mail::assertSent(DeliveryAssignedMail::class, 1);
    expect(OrderDelivery::where('order_id', $order->id)->count())->toBe(1);
});

// ---------------------------------------------------------------------
//  TEST 3 — Assignment changes from Partner A to Partner B
// ---------------------------------------------------------------------

test('TEST 3: reassigning to another partner notifies and emails only the new partner', function () {
    Mail::fake();

    $admin = dpnAdmin();
    $buyer = dpnBuyer();
    $partnerA = dpnPartner(['name' => 'Partner A']);
    $partnerB = dpnPartner(['name' => 'Partner B']);
    $order = dpnOrder($buyer);
    dpnItem($order);

    dpnAssign($this, $order, $partnerA, $admin)->assertRedirect();
    $delivery = OrderDelivery::where('order_id', $order->id)->sole();

    expect($partnerA->notifications()->count())->toBe(1);

    // Reassign A -> B.
    $this->actingAs($admin)
        ->post("/admin/deliveries/{$delivery->id}/reassign", ['delivery_partner_id' => $partnerB->id])
        ->assertRedirect();

    expect((int) $delivery->refresh()->delivery_partner_id)->toBe($partnerB->id);

    // Partner B got the new assignment notification + email.
    expect($partnerB->notifications()->where('data->type', 'delivery_assigned')->count())->toBe(1);
    Mail::assertSent(DeliveryAssignedMail::class, fn (DeliveryAssignedMail $mail) => $mail->hasTo($partnerB->email));

    // Partner A never receives another *assignment* notification for the order.
    expect($partnerA->notifications()->where('data->type', 'delivery_assigned')->count())->toBe(1);

    // A is still told the order moved away — that is not an assignment alert.
    expect($partnerA->notifications()->where('data->type', 'delivery_reassigned')->count())->toBe(1);
});

// ---------------------------------------------------------------------
//  TEST 4 — Buyer cannot reach delivery partner pages
// ---------------------------------------------------------------------

test('TEST 4: a buyer cannot access delivery partner order pages', function () {
    $admin = dpnAdmin();
    $buyer = dpnBuyer();
    $partner = dpnPartner();
    $order = dpnOrder($buyer);
    dpnItem($order);

    $delivery = dpnDelivery($order, $partner, $admin);

    $this->actingAs($buyer)->get("/delivery/deliveries/{$delivery->id}")->assertStatus(403);
    $this->actingAs($buyer)->get('/delivery/deliveries')->assertStatus(403);
    $this->actingAs($buyer)->get('/delivery/dashboard')->assertStatus(403);
});

// ---------------------------------------------------------------------
//  TEST 5 — Seller cannot reach a delivery partner's delivery page
// ---------------------------------------------------------------------

test('TEST 5: a seller cannot access a delivery partner assigned delivery page', function () {
    $admin = dpnAdmin();
    $buyer = dpnBuyer();
    $seller = dpnSeller();
    $partner = dpnPartner();
    $order = dpnOrder($buyer);
    dpnItem($order, $seller);

    $delivery = dpnDelivery($order, $partner, $admin);

    $this->actingAs($seller)->get("/delivery/deliveries/{$delivery->id}")->assertStatus(403);
    $this->actingAs($seller)->get('/delivery/dashboard')->assertStatus(403);
});

// ---------------------------------------------------------------------
//  TEST 6 — Partner A cannot open Partner B's assigned order
// ---------------------------------------------------------------------

test('TEST 6: a delivery partner cannot access another partner assigned order', function () {
    $admin = dpnAdmin();
    $buyer = dpnBuyer();
    $partnerA = dpnPartner(['name' => 'Partner A']);
    $partnerB = dpnPartner(['name' => 'Partner B']);
    $order = dpnOrder($buyer);
    dpnItem($order);

    $delivery = dpnDelivery($order, $partnerA, $admin);

    // B must not be able to see the order by editing the URL.
    $this->actingAs($partnerB)->get("/delivery/deliveries/{$delivery->id}")->assertStatus(403);
    $this->actingAs($partnerB)->post("/delivery/deliveries/{$delivery->id}/pickup")->assertStatus(403);

    expect($delivery->refresh()->status)->toBe('assigned');
});

// ---------------------------------------------------------------------
//  TEST 7 — Delivery partner without an email address
// ---------------------------------------------------------------------

test('TEST 7: a delivery partner without an email still gets assigned without crashing', function () {
    Mail::fake();
    Log::shouldReceive('warning')->once();

    $admin = dpnAdmin();
    $buyer = dpnBuyer();
    $partner = dpnPartner(['email' => '']);
    $order = dpnOrder($buyer);
    dpnItem($order);

    dpnAssign($this, $order, $partner, $admin)->assertRedirect()->assertSessionHasNoErrors();

    // Assignment still succeeded…
    $delivery = OrderDelivery::where('order_id', $order->id)->sole();
    expect((int) $delivery->delivery_partner_id)->toBe($partner->id);

    // …the in-app notification was still created…
    expect($partner->notifications()->count())->toBe(1);

    // …and no email was attempted for the missing address.
    Mail::assertNotSent(DeliveryAssignedMail::class);
});

// ---------------------------------------------------------------------
//  TEST 8 — Notification payload
// ---------------------------------------------------------------------

test('TEST 8: the in-app notification carries the order context and delivery url', function () {
    Mail::fake();

    $admin = dpnAdmin();
    $buyer = dpnBuyer();
    $partner = dpnPartner();
    $order = dpnOrder($buyer);
    dpnItem($order);

    dpnAssign($this, $order, $partner, $admin)->assertRedirect();

    $delivery = OrderDelivery::where('order_id', $order->id)->sole();
    $data = $partner->notifications()->sole()->data;

    expect($data['type'])->toBe('delivery_assigned');
    expect((int) $data['order_id'])->toBe($order->id);
    expect($data['order_number'])->toBe($order->order_number);
    expect((int) $data['delivery_partner_id'])->toBe($partner->id);
    expect($data['customer'])->toBe('Tobey Spider');
    expect((int) $data['items_count'])->toBe(1);
    expect((float) $data['total'])->toBe(273.02);
    expect($data['status'])->toBe('assigned');
    expect($data['url'])->toBe(route('delivery.deliveries.show', ['delivery' => $delivery->id]));

    // The title/message the bell UI renders are present too.
    expect($data['title'])->toBe('New delivery assigned');
    expect($data['message'])->toContain($order->order_number);

    // No seller financial data is ever attached to the notification.
    expect($data)->not->toHaveKeys(['bank_account', 'ifsc', 'upi_id', 'seller_payment']);
});

test('TEST 8b: opening the notification marks it read and lands on the delivery page', function () {
    Mail::fake();

    $admin = dpnAdmin();
    $buyer = dpnBuyer();
    $partner = dpnPartner();
    $order = dpnOrder($buyer);
    dpnItem($order);

    dpnAssign($this, $order, $partner, $admin)->assertRedirect();

    $delivery = OrderDelivery::where('order_id', $order->id)->sole();
    $notification = $partner->notifications()->sole();

    expect($notification->read_at)->toBeNull();
    expect($partner->unreadNotifications()->count())->toBe(1);

    $this->actingAs($partner)
        ->post("/notifications/{$notification->id}/read")
        ->assertRedirect(route('delivery.deliveries.show', ['delivery' => $delivery->id]));

    expect($notification->refresh()->read_at)->not->toBeNull();
    expect($partner->unreadNotifications()->count())->toBe(0);

    // The target page really is reachable for the assigned partner.
    $this->actingAs($partner)
        ->get(route('delivery.deliveries.show', ['delivery' => $delivery->id]))
        ->assertOk()
        ->assertSee($order->order_number);

    // The bell list renders the notification text (not an empty message).
    $this->actingAs($partner)
        ->get(route('notifications.index'))
        ->assertOk()
        ->assertSee('New delivery assigned')
        ->assertSee($order->order_number);
});

test('TEST 8c: a partner cannot open or mark read another partner notification', function () {
    Mail::fake();

    $admin = dpnAdmin();
    $buyer = dpnBuyer();
    $partnerA = dpnPartner(['name' => 'Partner A']);
    $partnerB = dpnPartner(['name' => 'Partner B']);
    $order = dpnOrder($buyer);
    dpnItem($order);

    dpnAssign($this, $order, $partnerA, $admin)->assertRedirect();

    $notification = $partnerA->notifications()->sole();

    $this->actingAs($partnerB)->post("/notifications/{$notification->id}/read")->assertStatus(404);
    expect($notification->refresh()->read_at)->toBeNull();
});

// ---------------------------------------------------------------------
//  TEST 9 — Email content
// ---------------------------------------------------------------------

test('TEST 9: the assignment email contains the order, customer, address, seller, items and status', function () {
    Mail::fake();

    $admin = dpnAdmin();
    $buyer = dpnBuyer();
    $seller = dpnSeller();
    $partner = dpnPartner();
    $order = dpnOrder($buyer);
    dpnItem($order, $seller);

    dpnAssign($this, $order, $partner, $admin)->assertRedirect();

    $captured = null;

    Mail::assertSent(DeliveryAssignedMail::class, function (DeliveryAssignedMail $mail) use (&$captured, $partner) {
        if (! $mail->hasTo($partner->email)) {
            return false;
        }

        $captured = $mail;

        return true;
    });

    expect($captured)->not->toBeNull();

    // Subject follows the documented "New Delivery Assigned - Order #..." shape.
    expect($captured->envelope()->subject)->toBe('New Delivery Assigned - Order #' . $order->order_number);

    $html = $captured->render();

    expect($html)->toContain($partner->name)                 // greeting
        ->toContain($order->order_number)                    // order number
        ->toContain('Tobey Spider')                          // customer
        ->toContain('908, Vivanta Complex')                  // delivery address
        ->toContain('Navsari')
        ->toContain('Sadie Sink')                            // seller / pickup
        ->toContain('IKEA MARKUS Ergonomic Office Chair')    // item
        ->toContain('Assigned')                              // delivery status
        ->toContain('Please call before arriving.');         // delivery instructions

    // No sensitive seller payment information leaks into the email.
    expect(strtolower($html))->not->toContain('ifsc')
        ->not->toContain('bank account')
        ->not->toContain('upi');
});

test('TEST 9b: a reassignment email tells the new partner it was reassigned', function () {
    Mail::fake();

    $admin = dpnAdmin();
    $buyer = dpnBuyer();
    $partnerA = dpnPartner(['name' => 'Partner A']);
    $partnerB = dpnPartner(['name' => 'Partner B']);
    $order = dpnOrder($buyer);
    dpnItem($order);

    dpnAssign($this, $order, $partnerA, $admin)->assertRedirect();
    $delivery = OrderDelivery::where('order_id', $order->id)->sole();

    $this->actingAs($admin)
        ->post("/admin/deliveries/{$delivery->id}/reassign", ['delivery_partner_id' => $partnerB->id])
        ->assertRedirect();

    $captured = null;

    Mail::assertSent(DeliveryAssignedMail::class, function (DeliveryAssignedMail $mail) use (&$captured, $partnerB) {
        if (! $mail->hasTo($partnerB->email)) {
            return false;
        }

        $captured = $mail;

        return true;
    });

    expect($captured->envelope()->subject)->toBe('Delivery Reassigned To You - Order #' . $order->order_number);
    expect($captured->render())->toContain('reassigned');
});

// ---------------------------------------------------------------------
//  Seller notifications stay separate from delivery partner ones
// ---------------------------------------------------------------------

test('the seller approval notification is unaffected by the delivery assignment', function () {
    Mail::fake();

    $admin = dpnAdmin();
    $buyer = dpnBuyer();
    $seller = dpnSeller();
    $partner = dpnPartner();
    $order = dpnOrder($buyer, 'pending');
    dpnItem($order, $seller);

    $this->actingAs($admin)->post("/admin/orders/{$order->id}/approve")->assertRedirect();

    // Seller gets their own approval notification + email…
    expect($seller->notifications()->count())->toBe(1);
    Mail::assertSent(App\Mail\SellerOrderApprovedMail::class, fn ($mail) => $mail->hasTo($seller->email));

    // …and the delivery partner is only notified once the order is assigned.
    expect($partner->notifications()->count())->toBe(0);
    Mail::assertNotSent(DeliveryAssignedMail::class);

    dpnAssign($this, $order->refresh(), $partner, $admin)->assertRedirect();

    expect($partner->notifications()->count())->toBe(1);
    expect($seller->notifications()->count())->toBe(1);
});

test('the delivery dashboard lists assigned deliveries with customer, address, seller and total', function () {
    Mail::fake();

    $admin = dpnAdmin();
    $buyer = dpnBuyer();
    $seller = dpnSeller();
    $partner = dpnPartner();
    $order = dpnOrder($buyer);
    dpnItem($order, $seller);

    dpnAssign($this, $order, $partner, $admin)->assertRedirect();

    $this->actingAs($partner)
        ->get('/delivery/dashboard')
        ->assertOk()
        ->assertSee('Assigned Deliveries')
        ->assertSee($order->order_number)   // order number
        ->assertSee('Tobey Spider')         // customer
        ->assertSee('Navsari')              // delivery address
        ->assertSee('Sadie Sink')           // seller / pickup
        ->assertSee('273.02')               // order total
        ->assertSee('View Delivery');
});

test('the delivery detail page shows the information needed to deliver the package', function () {
    Mail::fake();

    $admin = dpnAdmin();
    $buyer = dpnBuyer();
    $seller = dpnSeller();
    $partner = dpnPartner();
    $order = dpnOrder($buyer);
    dpnItem($order, $seller);

    dpnAssign($this, $order, $partner, $admin)->assertRedirect();
    $delivery = OrderDelivery::where('order_id', $order->id)->sole();

    $this->actingAs($partner)
        ->get("/delivery/deliveries/{$delivery->id}")
        ->assertOk()
        ->assertSee($order->order_number)
        ->assertSee('Tobey Spider')                      // customer name
        ->assertSee('9876543210')                        // customer phone
        ->assertSee('908, Vivanta Complex')              // shipping address
        ->assertSee('Navsari')                           // city
        ->assertSee('Gujarat')                           // state
        ->assertSee('396445')                            // postal code
        ->assertSee('India')                             // country
        ->assertSee('Sadie Sink')                        // seller / pickup
        ->assertSee('IKEA MARKUS Ergonomic Office Chair')
        ->assertSee('Please call before arriving.')      // delivery instructions
        ->assertSee('Assigned');                         // status
});

// ---------------------------------------------------------------------
//  Assignment notifications only target delivery partners
// ---------------------------------------------------------------------

test('only the assigned delivery partner account ever receives these notifications', function () {
    Mail::fake();

    $admin = dpnAdmin();
    $buyer = dpnBuyer();
    $seller = dpnSeller();
    $partner = dpnPartner();
    $order = dpnOrder($buyer);
    dpnItem($order, $seller);

    dpnAssign($this, $order, $partner, $admin)->assertRedirect();

    expect($partner->notifications()->count())->toBe(1);
    expect($buyer->notifications()->count())->toBe(0);
    expect($seller->notifications()->count())->toBe(0);
    expect($admin->notifications()->count())->toBe(0);

    Mail::assertSentCount(1);
});