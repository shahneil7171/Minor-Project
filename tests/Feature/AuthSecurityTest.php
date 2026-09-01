<?php

use App\Models\User;
use App\Services\CartService;
use Illuminate\Support\Facades\Hash;

test('login rejects invalid credentials with a generic error', function () {
    User::factory()->create(['email' => 'login-fail@example.com']);

    $this->post('/login', [
        'email' => 'login-fail@example.com',
        'password' => 'wrong-password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('login regenerates the session id', function () {
    User::factory()->create(['email' => 'session@example.com']);

    $this->get('/login');

    $oldId = session()->getId();

    $this->post('/login', [
        'email' => 'session@example.com',
        'password' => 'password',
    ])->assertRedirect('/');

    $this->assertAuthenticated();
    $this->assertNotSame($oldId, session()->getId());
});

test('logout invalidates the session and regenerates the csrf token', function () {
    $user = User::factory()->create(['email' => 'logout@example.com']);

    $this->actingAs($user)->get('/');

    $oldToken = session()->token();

    $this->post('/logout')->assertRedirect('/login');

    $this->assertGuest();
    $this->assertNotSame($oldToken, session()->token());
});

test('the persistent cart survives logout and login', function () {
    $buyer = User::factory()->create(['account_type' => 'buyer']);

    $this->actingAs($buyer)
        ->post('/cart/add/smart-watch-pro')
        ->assertStatus(302);

    $this->post('/logout');
    $this->assertGuest();

    $this->post('/login', [
        'email' => $buyer->email,
        'password' => 'password',
    ])->assertRedirect('/');

    // The product added before logout is still in the customer's cart.
    $this->assertSame(1, app(CartService::class)->count());
});

test('customers cannot access the admin panel', function () {
    $buyer = User::factory()->create(['account_type' => 'buyer']);

    $this->actingAs($buyer)->get('/admin/dashboard')->assertForbidden();
});

test('authenticated users are redirected away from guest-only pages', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/login')->assertRedirect(route('dashboard'));
    $this->actingAs($user)->get('/register')->assertRedirect(route('dashboard'));
});

test('users can change their password and remain signed in', function () {
    $user = User::factory()->create(['password' => 'OldPass1!']);

    $this->actingAs($user)
        ->post('/profile/change-password', [
            'current_password' => 'OldPass1!',
            'password' => 'NewPass123!',
            'password_confirmation' => 'NewPass123!',
        ])->assertRedirect(route('profile.show'));

    $this->assertTrue(Hash::check('NewPass123!', $user->fresh()->password));
    $this->assertAuthenticatedAs($user);
});

test('change password rejects an incorrect current password', function () {
    $user = User::factory()->create(['password' => 'OldPass1!']);

    $this->actingAs($user)
        ->post('/profile/change-password', [
            'current_password' => 'WrongPass1!',
            'password' => 'NewPass123!',
            'password_confirmation' => 'NewPass123!',
        ])->assertSessionHasErrors('current_password');

    $this->assertTrue(Hash::check('OldPass1!', $user->fresh()->password));
});

test('customers cannot change their account type via profile update', function () {
    $buyer = User::factory()->create(['account_type' => 'buyer']);

    $this->actingAs($buyer)->post('/profile/update', [
        'name' => $buyer->name,
        'email' => $buyer->email,
        'account_type' => 'admin',
    ]);

    $this->assertSame('buyer', $buyer->fresh()->account_type);
    $this->assertFalse($buyer->fresh()->isStaff());
});
