<?php

use App\Mail\RegistrationMail;
use App\Models\Order;
use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

/*
|--------------------------------------------------------------------------
| KDP MART — Role System (Buyer / Seller / Delivery Partner / Staff / Admin)
|--------------------------------------------------------------------------
| Covers the complete public registration role system plus the
| server-side authorization guarantees: public registration can never
| mint an admin, and lower-privileged roles can never reach another
| role's area or promote themselves.
*/

function roleRegistrationPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Role Tester',
        'email' => 'role-tester@example.com',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
        'account_type' => 'buyer',
    ], $overrides);
}

function makeStaffOrder(User $user, string $orderNumber = 'KDP-STAFF-001'): Order
{
    return Order::create([
        'user_id' => $user->id,
        'customer_email' => $user->email,
        'order_number' => $orderNumber,
        'status' => 'pending',
        'subtotal' => 100.00,
        'tax' => 0,
        'shipping_cost' => 0,
        'total' => 100.00,
        'payment_method' => 'cod',
        'shipping_name' => $user->name,
        'shipping_phone' => '9999999999',
        'shipping_address' => '1 Main St',
        'shipping_city' => 'Springfield',
        'shipping_state' => 'IL',
        'shipping_pincode' => '10001',
    ]);
}

// ---------------------------------------------------------------------------
// REGISTRATION UI
// ---------------------------------------------------------------------------

test('registration page offers buyer, seller, delivery partner and staff — never admin', function () {
    $this->get('/register')
        ->assertOk()
        ->assertSee('Account type')
        ->assertSee('>Buyer</label>', false)
        ->assertSee('>Seller</label>', false)
        ->assertSee('>Delivery Partner</label>', false)
        ->assertSee('>Staff</label>', false)
        ->assertDontSee('value="admin"', false);
});

// ---------------------------------------------------------------------------
// PUBLIC REGISTRATION
// ---------------------------------------------------------------------------

test('a buyer can register', function () {
    Mail::fake();

    $this->post('/register', roleRegistrationPayload([
        'email' => 'new-buyer@example.com',
        'account_type' => 'buyer',
    ]))->assertRedirect('/');

    $user = User::where('email', 'new-buyer@example.com')->first();
    $this->assertNotNull($user);
    $this->assertSame('buyer', $user->account_type);
    $this->assertTrue($user->isActive());
    $this->assertAuthenticatedAs($user);

    Mail::assertSent(RegistrationMail::class, fn ($mail) => $mail->hasTo('new-buyer@example.com'));
});

test('a seller can register', function () {
    Mail::fake();

    $this->post('/register', roleRegistrationPayload([
        'email' => 'new-seller@example.com',
        'account_type' => 'seller',
    ]))->assertRedirect('/');

    $user = User::where('email', 'new-seller@example.com')->first();
    $this->assertNotNull($user);
    $this->assertSame('seller', $user->account_type);
    $this->assertTrue($user->isSeller());
});

test('a delivery partner can register and lands on the delivery dashboard', function () {
    Mail::fake();

    $this->post('/register', roleRegistrationPayload([
        'email' => 'new-partner@example.com',
        'account_type' => 'delivery_partner',
    ]))->assertRedirect(route('delivery.dashboard'));

    $user = User::where('email', 'new-partner@example.com')->first();
    $this->assertNotNull($user);
    $this->assertSame('delivery_partner', $user->account_type);
    $this->assertTrue($user->isDeliveryPartner());

    Mail::assertSent(RegistrationMail::class, fn ($mail) => $mail->hasTo('new-partner@example.com'));
});

test('staff can register and lands on the staff dashboard', function () {
    Mail::fake();

    $this->post('/register', roleRegistrationPayload([
        'email' => 'new-staff@example.com',
        'account_type' => 'staff',
    ]))->assertRedirect(route('staff.dashboard'));

    $user = User::where('email', 'new-staff@example.com')->first();
    $this->assertNotNull($user);
    $this->assertSame('staff', $user->account_type);
    $this->assertTrue($user->isStaffMember());
    // Staff are not admin panel staff.
    $this->assertFalse($user->isStaff());
    $this->assertFalse($user->isAdmin());

    Mail::assertSent(RegistrationMail::class, fn ($mail) => $mail->hasTo('new-staff@example.com'));
});

test('public registration can never create an admin account', function () {
    $this->post('/register', roleRegistrationPayload([
        'email' => 'evil-admin@example.com',
        'account_type' => 'admin',
    ]))->assertSessionHasErrors('account_type');

    $this->assertDatabaseMissing('users', ['email' => 'evil-admin@example.com']);
    $this->assertGuest();
});

test('privileged or unknown account types are rejected on registration', function () {
    foreach (['admin', 'Admin', 'ADMIN', 'manager', 'super-admin', ''] as $type) {
        $email = 'reject-' . md5((string) $type) . '@example.com';

        $this->post('/register', roleRegistrationPayload([
            'email' => $email,
            'account_type' => $type,
        ]))->assertSessionHasErrors('account_type');

        $this->assertDatabaseMissing('users', ['email' => $email]);
    }

    // Missing account type is rejected too (the form requires a choice).
    $this->post('/register', roleRegistrationPayload([
        'email' => 'no-type@example.com',
        'account_type' => null,
    ]))->assertSessionHasErrors('account_type');
    $this->assertDatabaseMissing('users', ['email' => 'no-type@example.com']);
});

// ---------------------------------------------------------------------------
// SERVER-SIDE AUTHORIZATION — DELIVERY PARTNER
// ---------------------------------------------------------------------------

test('delivery partners cannot access admin, seller or product management areas', function () {
    $partner = User::factory()->create(['account_type' => 'delivery_partner', 'status' => 'active']);

    foreach ([
        '/admin/dashboard',
        '/admin/orders',
        '/admin/customers',
        '/admin/system/users',
        '/admin/deliveries',
        '/admin/reports/sales',
        '/admin/settings',
        '/admin/delivery-partners',
    ] as $url) {
        $this->actingAs($partner)->get($url)->assertStatus(403);
    }

    $this->actingAs($partner)->get('/seller/orders')->assertStatus(403);

    // Product management is sellers/admins only.
    $this->actingAs($partner)->get('/products/create')
        ->assertRedirect(route('products'))
        ->assertSessionHas('error');
    $this->actingAs($partner)->post('/products', ['title' => 'Nope'])
        ->assertRedirect(route('products'))
        ->assertSessionHas('error');
});

test('delivery partners cannot promote themselves to admin', function () {
    $partner = User::factory()->create(['account_type' => 'delivery_partner']);

    $this->actingAs($partner)->post('/profile/update', [
        'name' => $partner->name,
        'email' => $partner->email,
        'phone' => '9999999999',
        'account_type' => 'admin',
    ])->assertRedirect(route('profile.show'));

    $this->assertSame('delivery_partner', $partner->fresh()->account_type);
    $this->assertFalse($partner->fresh()->isAdmin());

    // They also cannot reach the admin role-change endpoints at all.
    $this->actingAs($partner)->put('/admin/customers/' . $partner->id, [
        'name' => $partner->name,
        'email' => $partner->email,
        'status' => 'active',
        'account_type' => 'admin',
    ])->assertStatus(403);
    $this->assertSame('delivery_partner', $partner->fresh()->account_type);
});

// ---------------------------------------------------------------------------
// SERVER-SIDE AUTHORIZATION — STAFF
// ---------------------------------------------------------------------------

test('staff cannot access admin-only functionality', function () {
    $staff = User::factory()->create(['account_type' => 'staff']);

    foreach ([
        '/admin/dashboard',
        '/admin/orders',
        '/admin/customers',
        '/admin/system/users',
        '/admin/settings',
        '/admin/reports/sales',
        '/admin/coupons',
    ] as $url) {
        $this->actingAs($staff)->get($url)->assertStatus(403);
    }
});

test('staff cannot promote themselves to admin', function () {
    $staff = User::factory()->create(['account_type' => 'staff']);

    $this->actingAs($staff)->post('/profile/update', [
        'name' => $staff->name,
        'email' => $staff->email,
        'phone' => '9999999999',
        'account_type' => 'admin',
    ])->assertRedirect(route('profile.show'));

    $this->assertSame('staff', $staff->fresh()->account_type);
    $this->assertFalse($staff->fresh()->isAdmin());

    // No admin panel endpoint is reachable to change the own role either.
    $this->actingAs($staff)->put('/admin/system/users/' . $staff->id, [
        'name' => $staff->name,
        'email' => $staff->email,
        'account_type' => 'admin',
        'status' => 'active',
    ])->assertStatus(403);
    $this->assertSame('staff', $staff->fresh()->account_type);
});

test('staff can use the staff dashboard and read-only orders list', function () {
    // Explicit status mirrors the DeliveryPartnerTest fixture convention —
    // Eloquent does not read back DB column defaults into memory.
    $staff = User::factory()->create(['account_type' => 'staff', 'status' => 'active']);
    $buyer = User::factory()->create(['account_type' => 'buyer']);
    $order = makeStaffOrder($buyer);

    $this->actingAs($staff)->get('/staff/dashboard')
        ->assertOk()
        ->assertSee('Staff Dashboard')
        ->assertSee($order->order_number);

    $this->actingAs($staff)->get('/staff/orders?status=pending')
        ->assertOk()
        ->assertSee($order->order_number);
});

test('buyers, sellers and delivery partners cannot access the staff area', function () {
    foreach (['buyer', 'seller', 'delivery_partner'] as $type) {
        $user = User::factory()->create(['account_type' => $type]);

        $this->actingAs($user)->get('/staff/dashboard')->assertStatus(403);
        $this->actingAs($user)->get('/staff/orders')->assertStatus(403);
    }
});

// ---------------------------------------------------------------------------
// ADMIN MANAGEMENT OF ROLES
// ---------------------------------------------------------------------------

test('admin can create staff and delivery partner accounts', function () {
    $admin = User::factory()->create(['account_type' => 'admin']);

    $this->actingAs($admin)->post('/admin/system/users', [
        'name' => 'Ops Staff',
        'email' => 'ops-staff@example.com',
        'account_type' => 'staff',
        'password' => 'supersecret1',
    ])->assertRedirect(route('admin.system.users.index'));

    $this->assertDatabaseHas('users', [
        'email' => 'ops-staff@example.com',
        'account_type' => 'staff',
        'status' => 'active',
    ]);

    $this->actingAs($admin)->post('/admin/delivery-partners', [
        'name' => 'Route Partner',
        'email' => 'route-partner@example.com',
        'phone' => '9999999999',
        'password' => 'supersecret1',
    ])->assertRedirect(route('admin.delivery-partners.show', User::where('email', 'route-partner@example.com')->first()));

    $this->assertDatabaseHas('users', [
        'email' => 'route-partner@example.com',
        'account_type' => 'delivery_partner',
    ]);
});

test('admin can change user roles', function () {
    $admin = User::factory()->create(['account_type' => 'admin']);
    $buyer = User::factory()->create(['account_type' => 'buyer']);
    $staff = User::factory()->create(['account_type' => 'staff']);

    // Buyer -> Seller via the customers page.
    $this->actingAs($admin)->put('/admin/customers/' . $buyer->id, [
        'name' => $buyer->name,
        'email' => $buyer->email,
        'phone' => '9999999999',
        'status' => 'active',
        'account_type' => 'seller',
    ])->assertRedirect(route('admin.customers.show', $buyer));

    $this->assertSame('seller', $buyer->fresh()->account_type);

    // Staff -> Manager via System > Users.
    $this->actingAs($admin)->put('/admin/system/users/' . $staff->id, [
        'name' => $staff->name,
        'email' => $staff->email,
        'account_type' => 'manager',
        'status' => 'active',
    ])->assertRedirect(route('admin.system.users.index'));

    $this->assertSame('manager', $staff->fresh()->account_type);
});

test('admin can enable and disable staff accounts', function () {
    $admin = User::factory()->create(['account_type' => 'admin']);
    $staff = User::factory()->create(['account_type' => 'staff']);

    $this->actingAs($admin)->put('/admin/system/users/' . $staff->id, [
        'name' => $staff->name,
        'email' => $staff->email,
        'account_type' => 'staff',
        'status' => 'inactive',
    ])->assertRedirect(route('admin.system.users.index'));

    $this->assertSame('inactive', $staff->fresh()->status);

    // Stop acting as the admin before hitting the guest-only login route —
    // authenticated users are redirected away from /login without errors.
    $this->post('/logout');

    // Disabled staff cannot sign in.
    $this->post('/login', ['email' => $staff->email, 'password' => 'password'])
        ->assertSessionHasErrors('email');
    $this->assertGuest();

    // Re-enabled staff can sign in again and reach their area.
    $this->actingAs($admin)->put('/admin/system/users/' . $staff->id, [
        'name' => $staff->name,
        'email' => $staff->email,
        'account_type' => 'staff',
        'status' => 'active',
    ])->assertRedirect(route('admin.system.users.index'));

    $this->post('/logout');

    $this->post('/login', ['email' => $staff->email, 'password' => 'password'])
        ->assertRedirect(route('staff.dashboard'));
    $this->assertAuthenticated();
});

test('only admins can assign the admin role', function () {
    $group = UserGroup::create([
        'name' => 'Full Access',
        'slug' => 'full-access',
        'permissions' => ['*'],
        'is_default' => false,
    ]);

    $manager = User::factory()->create(['account_type' => 'manager', 'user_group_id' => $group->id]);
    $admin = User::factory()->create(['account_type' => 'admin']);

    // A privileged manager still cannot mint an administrator…
    $this->actingAs($manager)->post('/admin/system/users', [
        'name' => 'Sneaky Admin',
        'email' => 'sneaky-admin@example.com',
        'account_type' => 'admin',
        'password' => 'supersecret1',
    ])->assertStatus(403);

    $this->assertDatabaseMissing('users', ['email' => 'sneaky-admin@example.com']);

    // …cannot edit or demote an administrator account…
    $this->actingAs($manager)->get('/admin/system/users/' . $admin->id . '/edit')->assertStatus(403);
    $this->actingAs($manager)->put('/admin/system/users/' . $admin->id, [
        'name' => $admin->name,
        'email' => $admin->email,
        'account_type' => 'manager',
        'status' => 'active',
    ])->assertStatus(403);
    $this->assertSame('admin', $admin->fresh()->account_type);

    // …but CAN create staff accounts.
    $this->actingAs($manager)->post('/admin/system/users', [
        'name' => 'Managed Staff',
        'email' => 'managed-staff@example.com',
        'account_type' => 'staff',
        'password' => 'supersecret1',
    ])->assertRedirect(route('admin.system.users.index'));

    $this->assertDatabaseHas('users', [
        'email' => 'managed-staff@example.com',
        'account_type' => 'staff',
    ]);
});

// ---------------------------------------------------------------------------
// LOGIN / LOGOUT / PASSWORDS / PROFILE
// ---------------------------------------------------------------------------

test('every role lands on its own area after login and can log out', function () {
    $cases = [
        'buyer' => ['account_type' => 'buyer', 'expected' => '/'],
        'seller' => ['account_type' => 'seller', 'expected' => '/'],
        'delivery_partner' => ['account_type' => 'delivery_partner', 'expected' => route('delivery.dashboard')],
        'staff' => ['account_type' => 'staff', 'expected' => route('staff.dashboard')],
        'admin' => ['account_type' => 'admin', 'expected' => route('admin.dashboard')],
    ];

    foreach ($cases as $key => $case) {
        $user = User::factory()->create([
            'email' => 'login-' . $key . '@example.com',
            'account_type' => $case['account_type'],
        ]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect($case['expected']);

        $this->assertAuthenticatedAs($user);

        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }
});

test('delivery partners and staff can change their password', function () {
    foreach (['delivery_partner', 'staff'] as $type) {
        $user = User::factory()->create([
            'account_type' => $type,
            'password' => 'OldPass1!',
        ]);

        $this->actingAs($user)->post('/profile/change-password', [
            'current_password' => 'OldPass1!',
            'password' => 'NewPass123!',
            'password_confirmation' => 'NewPass123!',
        ])->assertRedirect(route('profile.show'));

        $this->assertTrue(Hash::check('NewPass123!', $user->fresh()->password));
    }
});

test('users can see their account type on their profile', function () {
    $partner = User::factory()->create(['account_type' => 'delivery_partner']);
    $staff = User::factory()->create(['account_type' => 'staff']);

    $this->actingAs($partner)->get('/profile')
        ->assertOk()
        ->assertSee('Account Type')
        ->assertSee('Delivery Partner');

    $this->actingAs($staff)->get('/profile')
        ->assertOk()
        ->assertSee('Account Type')
        ->assertSee('Staff');
});

// ---------------------------------------------------------------------------
// DATABASE / MIGRATION
// ---------------------------------------------------------------------------

test('the users table accepts every documented account type', function () {
    foreach (User::ACCOUNT_TYPES as $type) {
        $user = User::factory()->create([
            'account_type' => $type,
            'email' => 'type-' . $type . '@example.com',
        ]);

        $this->assertSame($type, $user->fresh()->account_type);
    }
});



