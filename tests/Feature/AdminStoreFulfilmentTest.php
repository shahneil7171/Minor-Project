<?php

namespace Tests\Feature;

use App\Mail\OrderApprovedMail;
use App\Mail\SellerOrderApprovedMail;
use App\Models\Order;
use App\Models\OrderDelivery;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Notifications\StoreAlert;
use App\Services\CartService;
use App\Services\InventoryService;
use App\Services\OrderStatusService;
use App\Support\FulfilmentOwner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * KDP MART — Admin-Owned Product Fulfilment & Seller Workflow.
 *
 * THE BUG THIS FILE COVERS:
 * Five products were added directly by the Admin store (Smart Watch Pro,
 * Signature Headphones, Premium Backpack, Samsung Galaxy S26 Ultra, Apple
 * iPhone 17 Pro Max). They have NO seller account. When a customer ordered one
 * and an admin approved it, the order displayed "Preparing by Seller" forever:
 * the only preparation steps in the workflow (confirmed -> processing ->
 * ready_for_pickup) belonged to a seller, and no seller owned those lines.
 *
 * The fix makes the Admin store a first-class FULFILMENT OWNER, exactly like a
 * seller, identified by OWNERSHIP (products/order_items.owner_type) rather
 * than by product name.
 */
class AdminStoreFulfilmentTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['account_type' => 'admin', 'status' => 'active']);
    }

    private function buyer(): User
    {
        return User::factory()->create(['account_type' => 'buyer', 'status' => 'active']);
    }

    private function seller(string $name = 'Seller One'): User
    {
        return User::factory()->create(['account_type' => 'seller', 'status' => 'active', 'name' => $name]);
    }

    private function partner(): User
    {
        return User::factory()->create(['account_type' => 'delivery_partner', 'status' => 'active']);
    }

    /**
     * A catalog product owned by the ADMIN STORE (no seller account).
     *
     * The slug is deliberately NOT one of the seeded catalog slugs
     * (TestCase::setUp seeds ProductsSeeder into every test database).
     */
    private function storeProduct(string $slug = 'admin-smart-watch-pro', string $title = 'Smart Watch Pro'): Product
    {
        return Product::create([
            'slug'       => $slug,
            'title'      => $title,
            'price'      => 199.00,
            'quantity'   => 10,
            'status'     => 1,
            'is_seed'    => true,
            'seller_id'  => null,
            'owner_type' => FulfilmentOwner::STORE,
        ]);
    }

    /**
     * A catalog product owned by a REGISTERED SELLER.
     *
     * 'ikea-chair' is not part of the seeded catalog, so it never collides.
     */
    private function sellerProduct(User $seller, string $slug = 'ikea-chair'): Product
    {
        return Product::create([
            'slug'       => $slug,
            'title'      => 'IKEA MARKUS Chair',
            'price'      => 273.02,
            'quantity'   => 10,
            'status'     => 1,
            'is_seed'    => false,
            'seller_id'  => $seller->id,
            'owner_type' => FulfilmentOwner::SELLER,
        ]);
    }

    /**
     * A legacy product whose ownership can no longer be established.
     */
    private function legacyProduct(string $slug = 'deleted-product'): Product
    {
        return Product::create([
            'slug'      => $slug,
            'title'     => 'Legacy Item',
            'price'     => 50.00,
            'quantity'  => 5,
            'status'    => 1,
            'seller_id' => null,
        ]);
    }

    private function makeOrder(User $buyer, string $status = 'pending'): Order
    {
        return Order::create([
            'user_id'          => $buyer->id,
            'customer_email'   => $buyer->email,
            'order_number'     => 'KDP-' . strtoupper(uniqid()),
            'status'           => $status,
            'subtotal'         => 199.00,
            'tax'              => 0,
            'shipping_cost'    => 0,
            'total'            => 199.00,
            'payment_method'   => 'cod',
            'shipping_name'    => $buyer->name,
            'shipping_phone'   => '9999999999',
            'shipping_address' => '1 Main St',
            'shipping_city'    => 'Springfield',
            'shipping_state'   => 'IL',
            'shipping_pincode' => '10001',
        ]);
    }

    private function addStoreLine(Order $order, string $title = 'Smart Watch Pro', int $qty = 1): OrderItem
    {
        return $order->items()->create([
            'product_slug'  => 'smart-watch-pro',
            'product_title' => $title,
            'sku'           => 'KDP-SMW-001',
            'price'         => 199.00,
            'quantity'      => $qty,
            'subtotal'      => 199.00 * $qty,
            'seller_id'     => null,
            'owner_type'    => FulfilmentOwner::STORE,
        ]);
    }

    private function addSellerLine(Order $order, User $seller, string $title = 'IKEA MARKUS Chair'): OrderItem
    {
        return $order->items()->create([
            'product_slug'  => 'ikea-chair',
            'product_title' => $title,
            'sku'           => 'CH-001',
            'price'         => 273.02,
            'quantity'      => 1,
            'subtotal'      => 273.02,
            'seller_id'     => $seller->id,
            'owner_type'    => FulfilmentOwner::SELLER,
        ]);
    }

    /**
     * A legacy line whose ownership marker is NULL — the state every
     * pre-migration order item was in before the backfill ran.
     */
    private function addLegacyLine(Order $order): OrderItem
    {
        return $order->items()->create([
            'product_slug'  => 'deleted-product',
            'product_title' => 'Legacy Item',
            'sku'           => 'LG-1',
            'price'         => 50.00,
            'quantity'      => 1,
            'subtotal'      => 50.00,
            'seller_id'     => null,
        ]);
    }

    private function checkoutPayload(): array
    {
        return [
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
        ];
    }

    // ===============================================================
    // 1 — Ownership identification (NOT product-name based)
    // ===============================================================

    public function test_admin_added_products_are_identified_as_store_owned_by_ownership(): void
    {
        $store = $this->storeProduct();
        $sold  = $this->sellerProduct($this->seller());

        expect($store->isStoreOwned())->toBeTrue()
            ->and($store->isSellerOwned())->toBeFalse()
            ->and($sold->isSellerOwned())->toBeTrue()
            ->and($sold->isStoreOwned())->toBeFalse();
    }

    public function test_ownership_works_for_any_product_not_just_the_five_known_titles(): void
    {
        // A brand-new admin product with a completely unrelated name.
        $random = Product::create([
            'slug'       => 'brand-new-admin-item',
            'title'      => 'Some Totally Different Product',
            'price'      => 10,
            'quantity'   => 1,
            'seller_id'  => null,
            'owner_type' => FulfilmentOwner::STORE,
        ]);

        $order = $this->makeOrder($this->buyer(), 'confirmed');
        $order->items()->create([
            'product_slug'  => $random->slug,
            'product_title' => $random->title,
            'price'         => 10,
            'quantity'      => 1,
            'subtotal'      => 10,
            'seller_id'     => null,
            'owner_type'    => FulfilmentOwner::STORE,
        ]);

        expect($order->fresh()->hasStoreOwnedItems())->toBeTrue();
    }

    public function test_null_owner_type_falls_back_to_the_seller_id_convention(): void
    {
        $sellerRow = Product::create([
            'slug' => 'legacy-seller-item', 'title' => 'Legacy Seller Item',
            'price' => 10, 'quantity' => 1, 'seller_id' => $this->seller()->id,
        ]);

        $storeRow = $this->legacyProduct('legacy-store-item');

        expect($sellerRow->fulfilmentOwnerType())->toBe(FulfilmentOwner::SELLER)
            ->and($storeRow->fulfilmentOwnerType())->toBe(FulfilmentOwner::STORE);
    }

    public function test_legacy_line_with_unestablished_ownership_is_reported_not_guessed(): void
    {
        // A NULL marker follows the documented historical convention
        // ("seller_id IS NULL means platform-owned"), so such a line keeps
        // working as store-owned and can never dead-end an order.
        $order = $this->makeOrder($this->buyer(), 'confirmed');
        $line  = $this->addLegacyLine($order);

        expect($line->fulfilmentOwnerType())->toBe(FulfilmentOwner::STORE)
            ->and($line->isStoreOwned())->toBeTrue();

        // An EXPLICIT unknown marker is surfaced for review, never guessed
        // as either a seller or the store.
        $flagged = $order->items()->create([
            'product_slug'  => 'flagged-item',
            'product_title' => 'Flagged Legacy Item',
            'sku'           => 'LG-2',
            'price'         => 25.00,
            'quantity'      => 1,
            'subtotal'      => 25.00,
            'seller_id'     => null,
            'owner_type'    => FulfilmentOwner::UNKNOWN,
        ]);

        expect($flagged->fulfilmentOwnerType())->toBe(FulfilmentOwner::UNKNOWN)
            ->and($flagged->isUnknownOwnership())->toBeTrue()
            ->and($flagged->fulfilmentOwnerName())->toBe('Unassigned');
    }

    // ===============================================================
    // 2 — Admin can approve an Admin-owned order
    // ===============================================================

    public function test_admin_can_approve_an_order_of_admin_owned_products(): void
    {
        Notification::fake();

        $admin = $this->admin();
        $buyer = $this->buyer();
        $order = $this->makeOrder($buyer);
        $this->addStoreLine($order);

        $this->actingAs($admin)->post("/admin/orders/{$order->id}/approve")->assertRedirect();

        $order->refresh();
        expect($order->status)->toBe('confirmed');
        expect($order->approved_at)->not->toBeNull();

        // The customer is told their order was confirmed.
        Notification::assertSentTo($buyer, StoreAlert::class);
    }

    public function test_approving_an_admin_owned_order_does_not_claim_it_is_already_packaged(): void
    {
        $admin = $this->admin();
        $buyer = $this->buyer();
        $order = $this->makeOrder($buyer);
        $this->addStoreLine($order);

        $this->actingAs($admin)->post("/admin/orders/{$order->id}/approve")->assertRedirect();

        $order->refresh()->load('items');

        // Confirmed — but nothing has been packed and it is NOT ready for pickup.
        expect($order->status)->toBe('confirmed')
            ->and($order->allFulfilmentOwnersReady())->toBeFalse()
            ->and($order->storeFulfilmentPending())->toBeTrue();
    }

    // ===============================================================
    // 3 — Admin-owned products enter the STORE preparation workflow
    // ===============================================================

    public function test_admin_owned_order_enters_the_store_preparation_workflow(): void
    {
        $admin = $this->admin();
        $buyer = $this->buyer();
        $order = $this->makeOrder($buyer, 'confirmed');
        $this->addStoreLine($order);

        // It is visible in the Admin Store Fulfilment queue…
        $this->actingAs($admin)->get('/admin/store-orders')
            ->assertOk()
            ->assertSee($order->order_number);

        // …and NOT in any seller's queue.
        $this->actingAs($this->seller())->get('/seller/orders')
            ->assertOk()
            ->assertDontSee($order->order_number);

        // The store can start preparing it.
        $this->actingAs($admin)->post("/admin/store-orders/{$order->id}/status", ['status' => 'processing'])
            ->assertRedirect();

        expect($order->fresh()->status)->toBe('processing');
    }

    // ===============================================================
    // 4 — Admin can mark its store's items Ready for Pickup
    // ===============================================================

    public function test_admin_can_mark_store_items_ready_for_pickup(): void
    {
        $admin = $this->admin();
        $buyer = $this->buyer();
        $order = $this->makeOrder($buyer, 'confirmed');
        $this->addStoreLine($order);

        $this->actingAs($admin)->post("/admin/store-orders/{$order->id}/status", ['status' => 'processing']);
        $this->actingAs($admin)->post("/admin/store-orders/{$order->id}/status", ['status' => 'ready_for_pickup'])
            ->assertRedirect();

        $order->refresh()->load('items');

        expect($order->status)->toBe('ready_for_pickup')
            ->and($order->ready_for_pickup_at)->not->toBeNull()
            ->and($order->allFulfilmentOwnersReady())->toBeTrue()
            ->and($order->items->first()->isFulfilmentReady())->toBeTrue();

        // The transition is recorded in the status history.
        $this->assertDatabaseHas('order_status_histories', [
            'order_id'   => $order->id,
            'to_status'  => 'ready_for_pickup',
            'changed_by' => $admin->id,
        ]);
    }

    public function test_store_can_only_take_its_two_preparation_steps(): void
    {
        $admin = $this->admin();
        $buyer = $this->buyer();
        $order = $this->makeOrder($buyer, 'confirmed');
        $this->addStoreLine($order);

        // The store may not jump straight to a delivery-owned status.
        $this->actingAs($admin)->post("/admin/store-orders/{$order->id}/status", ['status' => 'delivered'])
            ->assertStatus(403);

        expect($order->fresh()->status)->toBe('confirmed');
    }

    public function test_store_fulfilment_page_shows_the_stores_own_lines_and_owner_panel(): void
    {
        $admin  = $this->admin();
        $seller = $this->seller('Sadie Sink');
        $buyer  = $this->buyer();
        $order  = $this->makeOrder($buyer, 'confirmed');

        $this->addStoreLine($order, 'Smart Watch Pro');
        $this->addSellerLine($order, $seller, 'IKEA MARKUS Chair');

        $this->actingAs($admin)->get("/admin/store-orders/{$order->id}")
            ->assertOk()
            ->assertSee('Smart Watch Pro')
            ->assertSee('Fulfilment Owners')
            ->assertSee('Sadie Sink');      // shown in the owner panel
    }

    public function test_store_fulfilment_page_404s_for_an_order_without_store_products(): void
    {
        $admin  = $this->admin();
        $seller = $this->seller();
        $buyer  = $this->buyer();
        $order  = $this->makeOrder($buyer, 'confirmed');
        $this->addSellerLine($order, $seller);

        $this->actingAs($admin)->get("/admin/store-orders/{$order->id}")->assertStatus(404);
    }

    // ===============================================================
    // 5 — Seller-owned products still use the seller workflow
    // ===============================================================

    public function test_seller_owned_products_still_enter_the_seller_workflow(): void
    {
        $seller = $this->seller();
        $buyer  = $this->buyer();
        $order  = $this->makeOrder($buyer, 'confirmed');
        $this->addSellerLine($order, $seller);

        // Visible to the seller…
        $this->actingAs($seller)->get('/seller/orders')
            ->assertOk()
            ->assertSee($order->order_number);

        // …and NOT in the Admin store fulfilment queue.
        $this->actingAs($this->admin())->get('/admin/store-orders')
            ->assertOk()
            ->assertDontSee($order->order_number);

        // The seller performs their own two preparation steps.
        $this->actingAs($seller)->post("/seller/orders/{$order->id}/status", ['status' => 'processing']);
        $this->actingAs($seller)->post("/seller/orders/{$order->id}/status", ['status' => 'ready_for_pickup']);

        expect($order->fresh()->status)->toBe('ready_for_pickup');
    }

    public function test_admin_cannot_prepare_a_sellers_items_for_them(): void
    {
        $admin  = $this->admin();
        $seller = $this->seller();
        $buyer  = $this->buyer();
        $order  = $this->makeOrder($buyer, 'confirmed');
        $this->addSellerLine($order, $seller);

        // The generic admin status route refuses the seller's packaging step.
        $this->actingAs($admin)->post("/admin/orders/{$order->id}/status", ['status' => 'processing'])
            ->assertStatus(403);

        expect($order->fresh()->status)->toBe('confirmed');
    }

    public function test_one_seller_cannot_prepare_another_sellers_products(): void
    {
        $sellerA = $this->seller('Seller A');
        $sellerB = $this->seller('Seller B');
        $buyer   = $this->buyer();
        $order   = $this->makeOrder($buyer, 'confirmed');
        $this->addSellerLine($order, $sellerA);

        $this->actingAs($sellerB)->post("/seller/orders/{$order->id}/status", ['status' => 'processing'])
            ->assertStatus(403);

        $this->actingAs($sellerB)->get("/seller/orders/{$order->id}")->assertStatus(403);

        expect($order->fresh()->status)->toBe('confirmed');
    }

    public function test_one_seller_cannot_prepare_the_stores_products(): void
    {
        $seller = $this->seller();
        $buyer  = $this->buyer();
        $order  = $this->makeOrder($buyer, 'confirmed');
        $this->addStoreLine($order);

        $this->actingAs($seller)->post("/seller/orders/{$order->id}/status", ['status' => 'processing'])
            ->assertStatus(403);

        expect($order->fresh()->status)->toBe('confirmed');
    }

    // ===============================================================
    // 6 — Customers and delivery partners cannot change fulfilment
    // ===============================================================

    public function test_customers_cannot_update_fulfilment_statuses(): void
    {
        $buyer = $this->buyer();
        $order = $this->makeOrder($buyer, 'confirmed');
        $this->addStoreLine($order);

        $this->actingAs($buyer)->post("/admin/store-orders/{$order->id}/status", ['status' => 'processing'])
            ->assertStatus(403);

        $this->actingAs($buyer)->post("/admin/orders/{$order->id}/status", ['status' => 'processing'])
            ->assertStatus(403);

        $this->actingAs($buyer)->post("/seller/orders/{$order->id}/status", ['status' => 'ready_for_pickup'])
            ->assertStatus(403);

        $this->actingAs($buyer)->get('/admin/store-orders')->assertStatus(403);

        expect($order->fresh()->status)->toBe('confirmed');
    }

    public function test_delivery_partner_can_update_only_their_own_delivery_task(): void
    {
        $admin = $this->admin();
        $buyer = $this->buyer();
        $mine  = $this->partner();
        $other = $this->partner();
        $order = $this->makeOrder($buyer, 'ready_for_pickup');
        $this->addStoreLine($order);

        $this->actingAs($admin)->post("/admin/orders/{$order->id}/assign-delivery", [
            'delivery_partner_id' => $mine->id,
        ]);

        $delivery = OrderDelivery::where('order_id', $order->id)->sole();

        // The assigned partner may move their own task forward…
        $this->actingAs($mine)->post("/delivery/deliveries/{$delivery->id}/pickup")->assertRedirect();
        expect($delivery->fresh()->status)->toBe('picked_up');

        // …but a different partner may not touch it.
        $this->actingAs($other)->post("/delivery/deliveries/{$delivery->id}/out-for-delivery")
            ->assertStatus(403);
        expect($delivery->fresh()->status)->toBe('picked_up');
    }

    public function test_a_seller_may_not_update_a_delivery_task(): void
    {
        $admin  = $this->admin();
        $seller = $this->seller();
        $buyer  = $this->buyer();
        $order  = $this->makeOrder($buyer, 'ready_for_pickup');
        $this->addSellerLine($order, $seller);

        $this->actingAs($admin)->post("/admin/orders/{$order->id}/assign-delivery", [
            'delivery_partner_id' => $this->partner()->id,
        ]);

        $delivery = OrderDelivery::where('order_id', $order->id)->sole();

        $this->actingAs($seller)->post("/delivery/deliveries/{$delivery->id}/pickup")->assertStatus(403);
        expect($delivery->fresh()->status)->toBe('assigned');
    }

    // ===============================================================
    // 7 — Mixed Admin/seller orders do not become Ready prematurely
    // ===============================================================

    public function test_mixed_order_does_not_become_ready_while_a_seller_is_still_packing(): void
    {
        $admin  = $this->admin();
        $seller = $this->seller();
        $buyer  = $this->buyer();
        $order  = $this->makeOrder($buyer, 'processing');

        $this->addStoreLine($order);
        $this->addSellerLine($order, $seller);

        expect($order->isMixedOwnership())->toBeTrue();

        // The store packs its own portion first.
        $this->actingAs($admin)->post("/admin/store-orders/{$order->id}/status", ['status' => 'ready_for_pickup'])
            ->assertRedirect();

        $order->refresh()->load('items');

        // The shared order must NOT advance: the seller has not packed yet.
        expect($order->status)->toBe('processing')
            ->and($order->storeFulfilmentPending())->toBeFalse()
            ->and($order->allFulfilmentOwnersReady())->toBeFalse();

        // The service itself also refuses the transition (backstop guard).
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(OrderStatusService::class)->transition($order->fresh(), 'ready_for_pickup', $admin);
    }

    public function test_mixed_order_becomes_ready_only_after_every_owner_packs(): void
    {
        $admin  = $this->admin();
        $seller = $this->seller();
        $buyer  = $this->buyer();
        $order  = $this->makeOrder($buyer, 'processing');

        $this->addStoreLine($order);
        $this->addSellerLine($order, $seller);

        // Store packs first — order stays in Processing.
        $this->actingAs($admin)->post("/admin/store-orders/{$order->id}/status", ['status' => 'ready_for_pickup']);
        expect($order->fresh()->status)->toBe('processing');

        // The seller finishes — now (and only now) the order advances.
        $this->actingAs($seller)->post("/seller/orders/{$order->id}/status", ['status' => 'ready_for_pickup']);

        $order->refresh();
        expect($order->status)->toBe('ready_for_pickup')
            ->and($order->allFulfilmentOwnersReady())->toBeTrue();
    }

    public function test_mixed_order_reverse_order_also_waits_for_the_store(): void
    {
        $admin  = $this->admin();
        $seller = $this->seller();
        $buyer  = $this->buyer();
        $order  = $this->makeOrder($buyer, 'processing');

        $this->addStoreLine($order);
        $this->addSellerLine($order, $seller);

        // The seller packs first — the order must still wait for the store.
        $this->actingAs($seller)->post("/seller/orders/{$order->id}/status", ['status' => 'ready_for_pickup']);

        expect($order->fresh()->status)->toBe('processing');

        // The store finishes — the order advances.
        $this->actingAs($admin)->post("/admin/store-orders/{$order->id}/status", ['status' => 'ready_for_pickup']);

        expect($order->fresh()->status)->toBe('ready_for_pickup');
    }

    public function test_a_delivery_partner_cannot_be_assigned_before_every_owner_is_ready(): void
    {
        $admin  = $this->admin();
        $seller = $this->seller();
        $buyer  = $this->buyer();
        $order  = $this->makeOrder($buyer, 'processing');

        $this->addStoreLine($order);
        $this->addSellerLine($order, $seller);

        $this->actingAs($admin)->post("/admin/store-orders/{$order->id}/status", ['status' => 'ready_for_pickup']);

        $this->actingAs($admin)->post("/admin/orders/{$order->id}/assign-delivery", [
            'delivery_partner_id' => $this->partner()->id,
        ])->assertSessionHas('error');

        $this->assertDatabaseCount('order_deliveries', 0);
    }

    // ===============================================================
    // 8 — Labels reflect the ACTUAL fulfilment owner
    // ===============================================================

    public function test_customer_order_page_labels_the_real_owner_not_a_hardcoded_seller(): void
    {
        $buyer = $this->buyer();
        $order = $this->makeOrder($buyer, 'processing');
        $this->addStoreLine($order);

        $this->actingAs($buyer)->get("/orders/{$order->id}")
            ->assertOk()
            ->assertSee('Preparing by ' . FulfilmentOwner::storeOwnerLabel())
            ->assertDontSee('Preparing by Seller');
    }

    public function test_admin_orders_index_labels_the_real_owner(): void
    {
        $admin = $this->admin();
        $buyer = $this->buyer();
        $order = $this->makeOrder($buyer, 'processing');
        $this->addStoreLine($order);

        $this->actingAs($admin)->get('/admin/orders')
            ->assertOk()
            ->assertSee('Preparing by ' . FulfilmentOwner::storeOwnerLabel())
            ->assertDontSee('Preparing by Seller');
    }

    public function test_seller_owned_order_keeps_the_seller_specific_label(): void
    {
        $admin  = $this->admin();
        $seller = $this->seller('Sadie Sink');
        $buyer  = $this->buyer();
        $order  = $this->makeOrder($buyer, 'processing');
        $this->addSellerLine($order, $seller);

        expect($order->fresh()->fulfilmentPreparingLabel())->toBe('Preparing by Sadie Sink');

        $this->actingAs($admin)->get('/admin/orders')
            ->assertOk()
            ->assertSee('Preparing by Sadie Sink');
    }

    public function test_mixed_order_label_names_both_owners(): void
    {
        $seller = $this->seller('Sadie Sink');
        $buyer  = $this->buyer();
        $order  = $this->makeOrder($buyer, 'processing');
        $this->addStoreLine($order);
        $this->addSellerLine($order, $seller);

        $label = $order->fresh()->fulfilmentPreparingLabel();

        expect($label)->toContain(FulfilmentOwner::storeOwnerLabel())
            ->and($label)->toContain('Sadie Sink');
    }

    public function test_the_store_label_uses_the_configured_store_name(): void
    {
        expect(FulfilmentOwner::storeOwnerLabel())->toBe('KDP MART Store');
    }

    public function test_the_delivery_page_shows_the_real_pickup_owner(): void
    {
        $admin   = $this->admin();
        $buyer   = $this->buyer();
        $partner = $this->partner();
        $order   = $this->makeOrder($buyer, 'ready_for_pickup');
        $this->addStoreLine($order, 'Smart Watch Pro');

        $this->actingAs($admin)->post("/admin/orders/{$order->id}/assign-delivery", [
            'delivery_partner_id' => $partner->id,
        ]);

        $delivery = OrderDelivery::where('order_id', $order->id)->sole();

        // The delivery-partner route is partner-only; the partner assigned to
        // the task sees who actually packed the parcel.
        $this->actingAs($partner)->get("/delivery/deliveries/{$delivery->id}")
            ->assertOk()
            ->assertSee(FulfilmentOwner::storeOwnerLabel());
    }

    // ===============================================================
    // 9 — Notifications reach the correct roles without duplication
    // ===============================================================

    public function test_approving_a_store_order_notifies_the_customer_and_the_other_admins(): void
    {
        Notification::fake();

        $approver = $this->admin();
        $packer   = $this->admin();
        $buyer    = $this->buyer();
        $order    = $this->makeOrder($buyer);
        $this->addStoreLine($order);

        $this->actingAs($approver)->post("/admin/orders/{$order->id}/approve")->assertRedirect();

        // The customer is notified…
        Notification::assertSentTo($buyer, StoreAlert::class);

        // …the OTHER administrator is asked to prepare the store's items…
        Notification::assertSentTo($packer, StoreAlert::class, function (StoreAlert $n) use ($order) {
            return ($n->meta['type'] ?? null) === 'store_order_approved'
                && ($n->meta['order_id'] ?? null) === $order->id;
        });

        // …and the approving admin is not notified about their own action.
        Notification::assertNotSentTo($approver, StoreAlert::class);
    }

    public function test_approving_a_seller_order_does_not_create_store_fulfilment_work(): void
    {
        Notification::fake();

        $admin  = $this->admin();
        $packer = $this->admin();
        $seller = $this->seller();
        $buyer  = $this->buyer();
        $order  = $this->makeOrder($buyer);
        $this->addSellerLine($order, $seller);

        $this->actingAs($admin)->post("/admin/orders/{$order->id}/approve")->assertRedirect();

        Notification::assertSentTo($seller, StoreAlert::class);

        // No store-fulfilment work was created for a purely seller order.
        Notification::assertNotSentTo($packer, StoreAlert::class, function (StoreAlert $n) {
            return ($n->meta['type'] ?? null) === 'store_order_approved';
        });
    }

    public function test_re_preparing_does_not_send_duplicate_notifications(): void
    {
        Notification::fake();

        $admin = $this->admin();
        $buyer = $this->buyer();
        $order = $this->makeOrder($buyer, 'processing');
        $this->addStoreLine($order);

        // First real transition.
        $this->actingAs($admin)->post("/admin/store-orders/{$order->id}/status", ['status' => 'ready_for_pickup']);
        $countAfterFirst = $buyer->notifications()->count();

        // Retrying the same step is rejected by the lifecycle, so it can never
        // re-notify anybody.
        $this->actingAs($admin)->post("/admin/store-orders/{$order->id}/status", ['status' => 'ready_for_pickup']);

        expect($buyer->notifications()->count())->toBe($countAfterFirst);
    }

    public function test_marking_store_items_ready_notifies_the_customer(): void
    {
        Notification::fake();

        $admin = $this->admin();
        $buyer = $this->buyer();
        $order = $this->makeOrder($buyer, 'processing');
        $this->addStoreLine($order);

        $this->actingAs($admin)->post("/admin/store-orders/{$order->id}/status", ['status' => 'ready_for_pickup'])
            ->assertRedirect();

        Notification::assertSentTo($buyer, StoreAlert::class, function (StoreAlert $n) {
            return str_contains($n->body, 'ready for pickup');
        });
    }

    public function test_a_mixed_order_notifies_admins_that_other_owners_are_still_packing(): void
    {
        Notification::fake();

        $admin  = $this->admin();
        $other  = $this->admin();
        $seller = $this->seller();
        $buyer  = $this->buyer();
        $order  = $this->makeOrder($buyer, 'processing');
        $this->addStoreLine($order);
        $this->addSellerLine($order, $seller);

        $this->actingAs($admin)->post("/admin/store-orders/{$order->id}/status", ['status' => 'ready_for_pickup']);

        Notification::assertSentTo($other, StoreAlert::class, function (StoreAlert $n) {
            return ($n->meta['type'] ?? null) === 'order_store_items_ready';
        });
    }

    // ===============================================================
    // 10 — Inventory, prices and totals are untouched
    // ===============================================================

    public function test_preparing_and_marking_ready_never_changes_money_or_stock(): void
    {
        $admin = $this->admin();
        $buyer = $this->buyer();

        $product = $this->storeProduct();
        $product->update(['quantity' => 7, 'reserved' => 3]);

        $order = $this->makeOrder($buyer, 'confirmed');
        $line  = $this->addStoreLine($order);

        $before = [
            'total'    => (float) $order->total,
            'subtotal' => (float) $order->subtotal,
            'tax'      => (float) $order->tax,
            'shipping' => (float) $order->shipping_cost,
            'discount' => (float) $order->discount_amount,
            'price'    => (float) $line->price,
            'payment'  => $order->payment_method,
        ];

        $this->actingAs($admin)->post("/admin/store-orders/{$order->id}/status", ['status' => 'processing']);
        $this->actingAs($admin)->post("/admin/store-orders/{$order->id}/status", ['status' => 'ready_for_pickup']);

        $order->refresh();
        $line->refresh();
        $product->refresh();

        expect((float) $order->total)->toBe($before['total'])
            ->and((float) $order->subtotal)->toBe($before['subtotal'])
            ->and((float) $order->tax)->toBe($before['tax'])
            ->and((float) $order->shipping_cost)->toBe($before['shipping'])
            ->and((float) $order->discount_amount)->toBe($before['discount'])
            ->and((float) $line->price)->toBe($before['price'])
            ->and($order->payment_method)->toBe($before['payment'])
            // Stock is neither deducted nor restored by a fulfilment step.
            ->and((int) $product->quantity)->toBe(7)
            ->and((int) $product->reserved)->toBe(3);
    }

    public function test_preparing_never_writes_an_inventory_transaction(): void
    {
        $admin = $this->admin();
        $buyer = $this->buyer();
        $order = $this->makeOrder($buyer, 'confirmed');
        $this->addStoreLine($order);

        $this->actingAs($admin)->post("/admin/store-orders/{$order->id}/status", ['status' => 'processing']);
        $this->actingAs($admin)->post("/admin/store-orders/{$order->id}/status", ['status' => 'ready_for_pickup']);

        $this->assertDatabaseCount('inventory_transactions', 0);
    }

}
