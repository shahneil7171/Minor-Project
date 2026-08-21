<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Review;
use App\Models\User;
use App\Models\WishlistItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCustomersTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['account_type' => 'admin']);
    }

    private function customer(array $attributes = []): User
    {
        return User::factory()->create(array_merge(['account_type' => 'buyer'], $attributes));
    }

    private function makeOrder(User $user, array $attributes = []): Order
    {
        return Order::create(array_merge([
            'user_id'       => $user->id,
            'customer_email' => $user->email,
            'order_number'  => 'KDP-' . strtoupper(uniqid()),
            'status'        => 'pending',
            'subtotal'      => 100.00,
            'tax'           => 0,
            'shipping_cost' => 0,
            'total'         => 100.00,
            'payment_method' => 'cod',
            'shipping_name' => $user->name,
            'shipping_phone' => '9999999999',
            'shipping_address' => '1 Main St',
            'shipping_city' => 'Springfield',
            'shipping_state' => 'IL',
            'shipping_pincode' => '10001',
        ], $attributes));
    }

    public function test_guests_are_redirected_from_customers(): void
    {
        $this->get('/admin/customers')->assertRedirect('/login');
    }

    public function test_non_admins_are_forbidden_from_customers(): void
    {
        $buyer = $this->customer();
        $seller = User::factory()->create(['account_type' => 'seller']);

        $this->actingAs($buyer)->get('/admin/customers')->assertStatus(403);
        $this->actingAs($seller)->get('/admin/customers')->assertStatus(403);
    }

    public function test_admin_sees_customer_list_with_statistics(): void
    {
        $admin = $this->admin();
        $active = $this->customer(['name' => 'Alice Walker', 'status' => 'active']);
        $blocked = $this->customer(['name' => 'Bob Stone', 'status' => 'blocked']);
        $this->makeOrder($active);

        $response = $this->actingAs($admin)->get('/admin/customers');

        $response->assertOk();
        $response->assertViewIs('admin.customers.index');
        $response->assertViewHas('stats', fn ($stats) => $stats['total'] === 2
            && $stats['active'] === 1
            && $stats['with_orders'] === 1);
        $response->assertSee('Alice Walker');
        $response->assertSee('Bob Stone');
        $response->assertSee('Total Customers');
        $response->assertSee('Active Customers');
        $response->assertSee('Customers This Month');
        $response->assertSee('Customers With Orders');
    }

    public function test_admin_accounts_are_not_listed_as_customers(): void
    {
        $admin = $this->admin();
        $this->customer(['name' => 'Real Customer']);

        $response = $this->actingAs($admin)->get('/admin/customers');

        $response->assertOk();
        // The customer table contains shoppers only — never staff accounts.
        $response->assertViewHas('customers', function ($customers) {
            return $customers->getCollection()->contains(fn ($c) => $c->name === 'Real Customer')
                && $customers->getCollection()->every(fn ($c) => $c->account_type !== 'admin');
        });
        $response->assertSee('Real Customer');
    }

    public function test_admin_can_search_customers_by_name_email_and_phone(): void
    {
        $admin = $this->admin();
        $alice = $this->customer(['name' => 'Alice Walker', 'email' => 'alice@example.com', 'phone' => '9876543210']);
        $bob = $this->customer(['name' => 'Bob Stone', 'email' => 'bob@example.com', 'phone' => '9123456780']);

        // By name
        $byName = $this->actingAs($admin)->get('/admin/customers?search=Alice&search_field=name');
        $byName->assertOk();
        $byName->assertSee('Alice Walker');
        $byName->assertDontSee('Bob Stone');

        // By email
        $byEmail = $this->actingAs($admin)->get('/admin/customers?search=bob@example.com&search_field=email');
        $byEmail->assertOk();
        $byEmail->assertSee('Bob Stone');
        $byEmail->assertDontSee('Alice Walker');

        // By phone
        $byPhone = $this->actingAs($admin)->get('/admin/customers?search=98765&search_field=phone');
        $byPhone->assertOk();
        $byPhone->assertSee('Alice Walker');
        $byPhone->assertDontSee('Bob Stone');

        // Across all fields
        $allFields = $this->actingAs($admin)->get('/admin/customers?search=example.com');
        $allFields->assertOk();
        $allFields->assertSee('Alice Walker');
        $allFields->assertSee('Bob Stone');

        $this->assertSame([$alice->id, $bob->id], [$alice->fresh()->id, $bob->fresh()->id]);
    }

    public function test_admin_can_filter_customers_by_status(): void
    {
        $admin = $this->admin();
        $this->customer(['name' => 'Active Ann', 'status' => 'active']);
        $this->customer(['name' => 'Blocked Bob', 'status' => 'blocked']);
        $this->customer(['name' => 'Inactive Ivy', 'status' => 'inactive']);

        $active = $this->actingAs($admin)->get('/admin/customers?status=active');
        $active->assertOk();
        $active->assertSee('Active Ann');
        $active->assertDontSee('Blocked Bob');

        $blocked = $this->actingAs($admin)->get('/admin/customers?status=blocked');
        $blocked->assertOk();
        $blocked->assertSee('Blocked Bob');
        $blocked->assertDontSee('Active Ann');

        $all = $this->actingAs($admin)->get('/admin/customers');
        $all->assertOk();
        $all->assertSee('Active Ann');
        $all->assertSee('Inactive Ivy');
    }

    public function test_admin_can_filter_customers_by_orders(): void
    {
        $admin = $this->admin();
        $withOrders = $this->customer(['name' => 'Shopper Sam']);
        $withoutOrders = $this->customer(['name' => 'Browser Bea']);
        $this->makeOrder($withOrders);

        $with = $this->actingAs($admin)->get('/admin/customers?orders=with');
        $with->assertOk();
        $with->assertSee('Shopper Sam');
        $with->assertDontSee('Browser Bea');

        $without = $this->actingAs($admin)->get('/admin/customers?orders=without');
        $without->assertOk();
        $without->assertSee('Browser Bea');
        $without->assertDontSee('Shopper Sam');
    }

    public function test_customer_details_show_profile_statistics_and_recent_orders(): void
    {
        $admin = $this->admin();
        $customer = $this->customer([
            'name' => 'Carla Cruz',
            'email' => 'carla@example.com',
            'phone' => '9000000001',
        ]);

        $this->makeOrder($customer, ['total' => 100.50, 'subtotal' => 100.50]);
        $this->makeOrder($customer, ['total' => 200.00, 'subtotal' => 200.00, 'status' => 'delivered']);

        WishlistItem::create(['user_id' => $customer->id, 'product_slug' => 'smart-watch-pro']);
        WishlistItem::create(['user_id' => $customer->id, 'product_slug' => 'leather-bag']);

        Review::create(['user_id' => $customer->id, 'product_slug' => 'smart-watch-pro', 'rating' => 5, 'comment' => 'Great!', 'status' => 'approved']);
        Review::create(['user_id' => $customer->id, 'product_slug' => 'leather-bag', 'rating' => 4, 'comment' => 'Nice', 'status' => 'pending']);

        $response = $this->actingAs($admin)->get('/admin/customers/' . $customer->id);

        $response->assertOk();
        $response->assertViewIs('admin.customers.show');
        // Profile
        $response->assertSee('Carla Cruz');
        $response->assertSee('carla@example.com');
        $response->assertSee('9000000001');
        $response->assertSee('Registration Date');
        // Statistics: 2 orders, 300.50 spent, 2 wishlist items, 2 reviews
        $response->assertViewHas('statistics', fn ($stats) => $stats['total_orders'] === 2
            && abs($stats['total_spent'] - 300.50) < 0.001
            && $stats['wishlist_items'] === 2
            && $stats['reviews_written'] === 2);
        // Recent orders list both order numbers
        $orderNumbers = $customer->orders()->pluck('order_number')->all();
        foreach ($orderNumbers as $orderNumber) {
            $response->assertSee($orderNumber);
        }
        $response->assertSee('Recent Orders');
        $response->assertSee('Order History');
        $response->assertSee('View tracking');
        $response->assertSee('View invoice');
    }

    public function test_admin_can_edit_a_customer(): void
    {
        $admin = $this->admin();
        $customer = $this->customer(['name' => 'Old Name', 'email' => 'old@example.com']);

        $response = $this->actingAs($admin)
            ->put('/admin/customers/' . $customer->id, [
                'name'   => 'New Name',
                'email'  => 'new@example.com',
                'phone'  => '9111111111',
                'status' => 'active',
            ]);

        $response->assertRedirect(route('admin.customers.show', $customer));

        $customer = $customer->fresh();
        $this->assertSame('New Name', $customer->name);
        $this->assertSame('new@example.com', $customer->email);
        $this->assertSame('9111111111', $customer->phone);

        // Email must stay unique.
        $other = $this->customer(['email' => 'taken@example.com']);
        $this->actingAs($admin)
            ->put('/admin/customers/' . $customer->id, [
                'name'   => $customer->name,
                'email'  => 'taken@example.com',
                'phone'  => $customer->phone,
                'status' => 'active',
            ])
            ->assertSessionHasErrors('email');
        $this->assertSame($other->id, User::where('email', 'taken@example.com')->first()->id);
    }

    public function test_admin_can_disable_and_enable_a_customer(): void
    {
        $admin = $this->admin();
        $customer = $this->customer();

        $disable = $this->actingAs($admin)
            ->post('/admin/customers/' . $customer->id . '/status', ['status' => 'blocked']);
        $disable->assertRedirect();
        $disable->assertSessionHas('success');
        $this->assertSame('blocked', $customer->fresh()->status);

        $enable = $this->actingAs($admin)
            ->post('/admin/customers/' . $customer->id . '/status', ['status' => 'active']);
        $enable->assertSessionHas('success');
        $this->assertSame('active', $customer->fresh()->status);

        // Invalid statuses are rejected.
        $this->actingAs($admin)
            ->post('/admin/customers/' . $customer->id . '/status', ['status' => 'banned'])
            ->assertSessionHasErrors('status');
    }

    public function test_admin_cannot_manage_admin_accounts_as_customers(): void
    {
        $admin = $this->admin();
        $otherAdmin = User::factory()->create(['account_type' => 'admin']);

        $this->actingAs($admin)->get('/admin/customers/' . $otherAdmin->id)->assertStatus(403);
        $this->actingAs($admin)->get('/admin/customers/' . $otherAdmin->id . '/edit')->assertStatus(403);
        $this->actingAs($admin)
            ->post('/admin/customers/' . $otherAdmin->id . '/status', ['status' => 'blocked'])
            ->assertStatus(403);
        $this->assertSame('active', $otherAdmin->fresh()->status);
    }

    public function test_admin_cannot_change_their_own_status(): void
    {
        $admin = $this->admin();

        // Admin accounts are staff, not customers: attempts to manage them
        // (including self) are rejected outright.
        $response = $this->actingAs($admin)
            ->post('/admin/customers/' . $admin->id . '/status', ['status' => 'blocked']);

        $response->assertStatus(403);
        $this->assertSame('active', $admin->fresh()->status);
    }

    public function test_blocked_customer_cannot_log_in(): void
    {
        $this->customer(['email' => 'blocked@example.com', 'status' => 'blocked']);

        $response = $this->post('/login', [
            'email'    => 'blocked@example.com',
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_inactive_customer_cannot_log_in(): void
    {
        $this->customer(['email' => 'inactive@example.com', 'status' => 'inactive']);

        $response = $this->post('/login', [
            'email'    => 'inactive@example.com',
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_active_customer_can_log_in(): void
    {
        $this->customer(['email' => 'happy@example.com', 'status' => 'active']);

        $response = $this->post('/login', [
            'email'    => 'happy@example.com',
            'password' => 'password',
        ]);

        $response->assertRedirect('/');
        $this->assertAuthenticated();
    }

    public function test_customers_are_paginated(): void
    {
        $admin = $this->admin();

        for ($i = 1; $i <= 20; $i++) {
            $this->customer([
                'name'       => 'Customer ' . str_pad((string) $i, 2, '0', STR_PAD_LEFT),
                'created_at' => now()->subDays(21 - $i),
            ]);
        }

        $pageOne = $this->actingAs($admin)->get('/admin/customers');
        $pageOne->assertOk();
        $pageOne->assertSee('Customer 20');
        $pageOne->assertDontSee('Customer 01');

        $pageTwo = $this->actingAs($admin)->get('/admin/customers?page=2');
        $pageTwo->assertOk();
        $pageTwo->assertSee('Customer 01');
        $pageTwo->assertDontSee('Customer 20');
    }

    public function test_admin_can_open_customer_order_tracking_and_invoice(): void
    {
        $admin = $this->admin();
        $customer = $this->customer();
        $order = $this->makeOrder($customer, ['order_number' => 'KDP-INV-001']);

        // Open order (admin can view any customer's order); tracking timeline included.
        $open = $this->actingAs($admin)->get('/orders/' . $order->id);
        $open->assertOk();
        $open->assertViewIs('orders.show');
        $open->assertSee('tracking');

        // Invoice renders a printable document.
        $invoice = $this->actingAs($admin)->get('/admin/orders/' . $order->id . '/invoice');
        $invoice->assertOk();
        $invoice->assertViewIs('admin.orders.invoice');
        $invoice->assertSee('KDP-INV-001');
        $invoice->assertSee('Billed to');
    }

    public function test_invoice_requires_admin(): void
    {
        $buyer = $this->customer();
        $order = $this->makeOrder($buyer);

        // Guests are bounced to the login page first.
        $this->get('/admin/orders/' . $order->id . '/invoice')->assertRedirect('/login');

        // Authenticated non-admins are forbidden.
        $this->actingAs($buyer)->get('/admin/orders/' . $order->id . '/invoice')->assertStatus(403);
    }

    public function test_buyers_still_cannot_view_other_users_orders(): void
    {
        $buyer = $this->customer();
        $other = $this->customer();
        $order = $this->makeOrder($other);

        $this->actingAs($buyer)->get('/orders/' . $order->id)->assertStatus(403);
    }
}