<?php

use App\Models\User;

test('login page is available', function () {
    $response = $this->get('/login');

    $response->assertStatus(200);
    $response->assertSee('Welcome to KDP SMART MART');
    $response->assertSee('Sign in to your account');
});

test('user can sign in with valid credentials', function () {
    $user = User::factory()->create([
        'email' => 'jane@example.com',
        'account_type' => 'buyer',
    ]);

    $this->post('/login', [
        'email' => 'jane@example.com',
        'password' => 'password',
    ])->assertRedirect('/');

    $this->assertAuthenticated();
});

test('users with invalid credentials are not signed in', function () {
    $user = User::factory()->create([
        'email' => 'jane@example.com',
        'account_type' => 'buyer',
    ]);

    $this->post('/login', [
        'email' => 'jane@example.com',
        'password' => 'wrong-password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});
