<?php

use App\Models\User;
use Illuminate\Support\Facades\Password;

test('forgot password page is available', function () {
    $response = $this->get('/forgot-password');

    $response->assertStatus(200);
    $response->assertSee('Forgot your password?');
    $response->assertSee('Send reset link');
});

test('users can request a password reset link', function () {
    $user = User::factory()->create(['email' => 'reset@example.com']);

    $response = $this->post('/forgot-password', ['email' => $user->email]);

    $response->assertRedirect('/forgot-password');
    $response->assertSessionHas('status');
    $this->assertDatabaseHas('password_reset_tokens', ['email' => $user->email]);
});

test('users can reset their password with a valid token', function () {
    $user = User::factory()->create(['email' => 'reset2@example.com']);
    $token = Password::createToken($user);

    $response = $this->post('/reset-password', [
        'token' => $token,
        'email' => $user->email,
        'password' => 'newpassword123',
        'password_confirmation' => 'newpassword123',
    ]);

    $response->assertRedirect('/dashboard');
    $this->assertTrue(auth()->check());
    $this->assertTrue(
        password_verify('newpassword123', $user->fresh()->password)
    );
});
