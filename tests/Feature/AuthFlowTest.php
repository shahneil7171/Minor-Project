<?php

use App\Mail\PasswordResetOtpMail;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

test('the landing page renders the storefront hero', function () {
    $this->get('/')
        ->assertStatus(200)
        ->assertSee('KDP MART')
        ->assertSee('Shop Smart.')
        ->assertSee('Shop Better.');
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
        ->assertRedirect(route('password.verify'))
        ->assertSessionHas('status', 'If that email address is registered with us, a 6-digit verification code has been sent to it.');

    Mail::assertSentCount(1);

    $this->assertDatabaseHas('password_reset_tokens', ['email' => $user->email]);
});

test('password reset responses do not reveal whether an account exists', function () {
    Mail::fake();

    $this->post('/forgot-password', ['email' => 'missing@example.com'])
        ->assertRedirect(route('password.verify'))
        ->assertSessionHas('status', 'If that email address is registered with us, a 6-digit verification code has been sent to it.');

    Mail::assertNothingSent();
    $this->assertDatabaseMissing('password_reset_tokens', ['email' => 'missing@example.com']);
});

test('a valid otp lets the user set a new password and sign in with it', function () {
    Mail::fake();

    $user = User::factory()->create(['email' => 'reset-user@example.com']);

    $this->post('/forgot-password', ['email' => $user->email])
        ->assertRedirect(route('password.verify'));

    $otp = null;
    Mail::assertSent(PasswordResetOtpMail::class, function (PasswordResetOtpMail $mail) use (&$otp) {
        $otp = $mail->build()->viewData['otp'];

        return true;
    });

    $this->post('/forgot-password/verify', ['email' => $user->email, 'otp' => $otp])
        ->assertRedirect(route('password.reset'));

    $this->post('/reset-password', [
        'email' => $user->email,
        'password' => 'NewSecure1!',
        'password_confirmation' => 'NewSecure1!',
    ])->assertRedirect(route('login'));

    // New password is stored hashed, the reset token is consumed.
    $this->assertTrue(Hash::check('NewSecure1!', $user->fresh()->password));
    $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);

    // The old password no longer works; the new one does.
    $this->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertSessionHasErrors();
    $this->assertGuest();

    $this->post('/login', ['email' => $user->email, 'password' => 'NewSecure1!'])
        ->assertRedirect('/');
    $this->assertAuthenticated();
});

test('an invalid otp is rejected safely', function () {
    Mail::fake();

    $user = User::factory()->create(['email' => 'otp-bad@example.com']);

    $this->post('/forgot-password', ['email' => $user->email]);

    $otp = null;
    Mail::assertSent(PasswordResetOtpMail::class, function (PasswordResetOtpMail $mail) use (&$otp) {
        $otp = $mail->build()->viewData['otp'];

        return true;
    });

    $wrongOtp = $otp === '000000' ? '000001' : '000000';

    $this->post('/forgot-password/verify', ['email' => $user->email, 'otp' => $wrongOtp])
        ->assertSessionHasErrors('otp');

    $this->assertGuest();
});

test('the reset page requires a verified email in the session', function () {
    $this->get('/reset-password')
        ->assertRedirect(route('password.request'));
});

test('authentication pages render for guests', function () {
    $this->get('/login')
        ->assertOk()
        ->assertSee('Sign in to your account');

    $this->get('/register')
        ->assertOk()
        ->assertSee('Create your account');

    $this->get('/forgot-password')
        ->assertOk()
        ->assertSee('Forgot password?');

    $this->get('/forgot-password/verify')
        ->assertOk()
        ->assertSee('Verify your code');
});
