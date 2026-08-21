<?php

namespace Tests\Feature;

use App\Mail\NewsletterMail;
use App\Models\Category;
use App\Models\Manufacturer;
use App\Models\NewsletterSubscriber;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductOption;
use App\Models\Promotion;
use App\Models\ReturnRequest;
use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['account_type' => 'admin']);
    }

    private function manager(): User
    {
        return User::factory()->create(['account_type' => 'manager']);
    }

    private function buyer(): User
    {
        return User::factory()->create(['account_type' => 'buyer']);
    }

    private function makeOrder(User $user, array $attributes = []): Order
    {
        return Order::create(array_merge([
            'user_id'          => $user->id,
            'customer_email'   => $user->email,
            'order_number'     => 'KDP-' . strtoupper(uniqid()),
            'status'           => 'pending',
            'subtotal'         => 100,
            'tax'              => 0,
            'shipping_cost'    => 0,
            'total'            => 100,
            'payment_method'   => 'cod',
            'shipping_name'    => $user->name,
            'shipping_phone'   => '9999999999',
            'shipping_address' => '1 Main St',
            'shipping_city'    => 'Springfield',
            'shipping_state'   => 'IL',
            'shipping_pincode' => '10001',
        ], $attributes));
    }

    public function test_dashboard_requires_staff_access(): void
    {
        $this->get('/admin/dashboard')->assertRedirect('/login');
        $this->actingAs($this->buyer())->get('/admin/dashboard')->assertStatus(403);
    }

    public function test_admin_sees_dashboard_cards_and_charts(): void
    {
        $admin = $this->admin();
        $this->makeOrder($this->buyer(), ['status' => 'pending', 'total' => 50, 'subtotal' => 50]);

        $response = $this->actingAs($admin)->get('/admin/dashboard');

        $response->assertOk();
        $response->assertViewIs('admin.dashboard');
        foreach (['Total Products', 'Total Orders', 'Total Customers', 'Revenue', 'Pending Orders', 'Reviews Pending'] as $card) {
            $response->assertSee($card);
        }
        $response->assertSee('ordersChart');
        $response->assertSee('revenueChart');
        $response->assertSee('Top Selling Products');
    }

    public function test_admin_can_manage_manufacturers(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/admin/manufacturers', [
            'name' => 'Samsung',
            'description' => 'Electronics giant',
            'status' => '1',
        ])->assertRedirect(route('admin.manufacturers.index'));

        $this->assertDatabaseHas('manufacturers', ['name' => 'Samsung']);

        $manufacturer = Manufacturer::where('name', 'Samsung')->first();
        $this->assertTrue($manufacturer->status);

        $this->actingAs($admin)
            ->put('/admin/manufacturers/' . $manufacturer->id, ['name' => 'Samsung Electronics', 'status' => '1'])
            ->assertRedirect(route('admin.manufacturers.index'));

        $this->assertSame('Samsung Electronics', $manufacturer->fresh()->name);

        $this->actingAs($admin)
            ->delete('/admin/manufacturers/' . $manufacturer->id)
            ->assertRedirect(route('admin.manufacturers.index'));

        $this->assertDatabaseMissing('manufacturers', ['name' => 'Samsung Electronics']);
    }

    public function test_manager_without_permissions_cannot_create_manufacturers(): void
    {
        $manager = $this->manager();

        $index = $this->actingAs($manager)->get('/admin/manufacturers');
        $index->assertOk(); // Managers may view…

        $this->actingAs($manager)
            ->post('/admin/manufacturers', ['name' => 'Sony'])
            ->assertStatus(403); // …but not create.
    }

    public function test_group_permissions_grant_actions(): void
    {
        $manager = $this->manager();
        $group = UserGroup::create([
            'name' => 'Catalog Editors',
            'slug' => 'catalog-editors',
            'permissions' => ['catalog.create'],
        ]);

        $manager->update(['user_group_id' => $group->id]);

        $this->actingAs($manager)
            ->post('/admin/manufacturers', ['name' => 'Apple', 'status' => '1'])
            ->assertRedirect(route('admin.manufacturers.index'));

        // Still no delete rights.
        $manufacturer = Manufacturer::where('name', 'Apple')->first();
        $this->actingAs($manager)
            ->delete('/admin/manufacturers/' . $manufacturer->id)
            ->assertStatus(403);
    }

    public function test_admin_can_manage_options(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/admin/options', [
            'name' => 'Color',
            'values' => "Red\nBlue\nGreen",
            'sort_order' => '1',
            'status' => '1',
        ])->assertRedirect(route('admin.options.index'));

        $option = ProductOption::where('name', 'Color')->first();
        $this->assertSame(['Red', 'Blue', 'Green'], $option->values);
        $this->assertSame('Red, Blue, Green', $option->valuesLabel());

        $list = $this->actingAs($admin)->get('/admin/options');
        $list->assertOk();
        $list->assertSee('Red, Blue, Green');
    }

    public function test_admin_can_manage_categories(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/admin/categories', [
            'name' => 'Gadgets',
            'is_active' => '1',
        ])->assertRedirect(route('admin.categories.index'));

        $category = Category::where('name', 'Gadgets')->first();
        $this->assertSame('gadgets', $category->slug);

        $this->actingAs($admin)->post('/admin/categories', [
            'name' => 'Smart Home',
            'parent_id' => (string) $category->id,
        ])->assertRedirect(route('admin.categories.index'));

        // Parent with children cannot be deleted.
        $this->actingAs($admin)->delete('/admin/categories/' . $category->id)
            ->assertRedirect(route('admin.categories.index'));
        $this->assertDatabaseHas('categories', ['name' => 'Gadgets']);
    }

    public function test_admin_can_create_and_update_returns(): void
    {
        $admin = $this->admin();
        $order = $this->makeOrder($this->buyer(), [
            'order_number' => 'KDP-RET-1',
            'status' => 'delivered',
            'total' => 80,
            'subtotal' => 80,
        ]);

        $this->actingAs($admin)->post('/admin/returns', [
            'order_id' => (string) $order->id,
            'product_title' => 'Smart Watch Pro',
            'reason' => 'Screen cracked on arrival',
            'status' => 'requested',
        ])->assertRedirect(route('admin.returns.index'));

        $return = ReturnRequest::first();
        $this->assertSame($order->id, $return->order_id);
        $this->assertSame($order->user_id, $return->user_id);

        $show = $this->actingAs($admin)->get('/admin/returns/' . $return->id);
        $show->assertOk();
        $show->assertSee('Smart Watch Pro');

        $this->actingAs($admin)
            ->post('/admin/returns/' . $return->id . '/status', ['status' => 'approved'])
            ->assertSessionHas('success');

        $this->assertSame('approved', $return->fresh()->status);
    }

    public function test_promotions_appear_on_storefront_when_active(): void
    {
        Promotion::create([
            'title' => 'Summer Mega Sale',
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'status' => true,
        ]);

        $home = $this->actingAs($this->buyer())->get('/');
        $home->assertOk();
        $home->assertSee('Summer Mega Sale');

        // Expired promotions are hidden.
        Promotion::query()->update(['end_date' => now()->subDays(5)->toDateString()]);

        $this->actingAs($this->buyer())->get('/')->assertOk()
            ->assertDontSee('Summer Mega Sale');
    }

    public function test_newsletter_subscribe_and_send(): void
    {
        Mail::fake();
        $admin = $this->admin();

        $this->actingAs($admin)->post('/admin/newsletter', [
            'email' => 'fan@example.com',
        ])->assertRedirect(route('admin.newsletter.index'));

        $this->assertDatabaseHas('newsletter_subscribers', ['email' => 'fan@example.com']);

        $compose = $this->actingAs($admin)->get('/admin/newsletter/compose');
        $compose->assertOk();
        $compose->assertSee('active subscriber');

        $this->actingAs($admin)->post('/admin/newsletter/send', [
            'subject' => 'Hello shoppers!',
            'body' => 'Big sale this weekend.',
        ])->assertSessionHas('success');

        Mail::assertSent(NewsletterMail::class, 1);
    }

    public function test_reports_pages_render_and_export_csv(): void
    {
        $admin = $this->admin();
        \App\Models\ProductView::recordView('smart-watch-pro', 'Smart Watch Pro');

        $this->actingAs($admin)->get('/admin/reports/sales')->assertOk()->assertViewIs('admin.reports.sales');
        $this->actingAs($admin)->get('/admin/reports/viewed')->assertOk()->assertSee('smart-watch-pro');
        $this->actingAs($admin)->get('/admin/reports/purchased')->assertOk()->assertViewIs('admin.reports.purchased');
        $this->actingAs($admin)->get('/admin/reports/customers')->assertOk()->assertSee('Highest Spending');

        $export = $this->actingAs($admin)->get('/admin/reports/sales/export?period=daily');
        $export->assertOk();
        $this->assertStringContainsString('text/csv', (string) $export->headers->get('Content-Type'));
    }

    public function test_products_purchased_report_aggregates_items(): void
    {
        $admin = $this->admin();
        $buyer = $this->buyer();

        $order = $this->makeOrder($buyer, [
            'order_number' => 'KDP-AGG-1',
            'status' => 'delivered',
            'total' => 200,
            'subtotal' => 200,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_slug' => 'smart-watch-pro',
            'product_title' => 'Smart Watch Pro',
            'price' => 100, 'quantity' => 2, 'subtotal' => 200,
        ]);

        $response = $this->actingAs($admin)->get('/admin/reports/purchased');
        $response->assertOk();
        $response->assertSee('Smart Watch Pro');
    }

    public function test_products_viewed_tracking_increments(): void
    {
        $this->actingAs($this->buyer())->get('/products/smart-watch-pro')->assertOk();
        $this->actingAs($this->buyer())->get('/products/smart-watch-pro')->assertOk();

        $view = \App\Models\ProductView::where('product_slug', 'smart-watch-pro')->first();
        $this->assertNotNull($view);
        $this->assertSame(2, $view->views);
    }

    public function test_system_users_crud_and_manager_flow(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/admin/system/users', [
            'name' => 'Mira Manager',
            'email' => 'mira@example.com',
            'account_type' => 'manager',
            'password' => 'supersecret1',
        ])->assertRedirect(route('admin.system.users.index'));

        $mira = User::where('email', 'mira@example.com')->first();
        $this->assertNotNull($mira);
        $this->assertTrue($mira->isStaff());

        // Manager can browse the panel…
        $this->actingAs($mira)->get('/admin/system/users')->assertOk();

        // …but cannot create staff users without permission.
        $this->actingAs($mira)->post('/admin/system/users', [
            'name' => 'Nope',
            'email' => 'nope@example.com',
            'account_type' => 'manager',
            'password' => 'supersecret1',
        ])->assertStatus(403);

        // Admin cannot delete themselves.
        $this->actingAs($admin)
            ->delete('/admin/system/users/' . $admin->id)
            ->assertSessionHas('error');
    }

    public function test_settings_save_and_persist(): void
    {
        $admin = $this->admin();

        $page = $this->actingAs($admin)->get('/admin/settings');
        $page->assertOk();
        $page->assertSee('Store Name');

        $this->actingAs($admin)->put('/admin/settings', [
            'store_name' => 'KDP MART HQ',
            'store_email' => 'hq@kdpmart.test',
            'store_phone' => '+91 99999 11111',
            'currency' => 'USD',
        ])->assertSessionHas('success');

        $this->assertSame('KDP MART HQ', \App\Models\Setting::get('store_name'));
        $this->assertSame('USD', \App\Models\Setting::get('currency'));

        // Admin panel branding follows the setting; storefront title unchanged.
        $panel = $this->actingAs($admin)->get('/admin/dashboard');
        $panel->assertSee('KDP MART HQ');

        $home = $this->actingAs($admin)->get('/');
        $home->assertOk()->assertSee('<title>KDP MART |', false);
    }

    public function test_backup_create_and_restore_roundtrip(): void
    {
        Storage::fake('local');

        $admin = $this->admin();
        Manufacturer::create(['name' => 'Canon', 'status' => true]);

        $this->actingAs($admin)->post('/admin/backups')->assertSessionHas('success');

        $files = Storage::disk('local')->files('backups');
        $this->assertCount(1, $files);

        // Wipe the data, then restore from the snapshot.
        Manufacturer::query()->delete();
        $this->assertDatabaseCount('manufacturers', 0);

        $this->actingAs($admin)
            ->post('/admin/backups/' . basename($files[0]) . '/restore')
            ->assertSessionHas('success');

        $this->assertDatabaseHas('manufacturers', ['name' => 'Canon']);
        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_legacy_admin_pages_render_inside_panel_layout(): void
    {
        $admin = $this->admin();

        foreach ([
            '/admin/orders'      => 'Manage Orders',
            '/admin/coupons'     => 'Coupons',
            '/admin/reviews'     => 'Manage Reviews',
            '/admin/customers'   => 'Customers',
        ] as $url => $marker) {
            $response = $this->actingAs($admin)->get($url);
            $response->assertOk();
            // Sidebar shell present + page content intact.
            $response->assertSee('ka-sidebar');
            $response->assertSee($marker);
        }
    }
}