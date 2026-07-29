<?php

use App\Models\User;
use Illuminate\Support\Facades\Mail;

test('the landing page renders the premium splash screen', function () {
    $this->get('/')
        ->assertStatus(200)
        ->assertSee('KDP MART')
        ->assertSee('Smart Shopping Platform');
});

test('unauthenticated users are redirected from the dashboard', function () {
    $this->get('/dashboard')
        ->assertRedirect('/login');
});

test('registered users receive a 6-digit otp by email', function () {
    Mail::fake();

    $user = User::factory()->create([
        'email' => 'otp-user@example.com',
    ]);

    $this->post('/forgot-password', ['email' => $user->email])
        ->assertRedirect('/forgot-password/verify')
        ->assertSessionHas('status', 'We sent a 6-digit verification code to your email address.');

    Mail::assertSentCount(1);

    $this->assertDatabaseHas('password_reset_tokens', ['email' => $user->email]);
});

test('unregistered emails are rejected with a clear message', function () {
    Mail::fake();

    $this->post('/forgot-password', ['email' => 'missing@example.com'])
        ->assertSessionHasErrors('email');

    Mail::assertNothingSent();
    $this->assertDatabaseMissing('password_reset_tokens', ['email' => 'missing@example.com']);
});
