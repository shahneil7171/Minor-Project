<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('registration page is available', function () {
    $response = $this->get('/register');

    $response->assertStatus(200);
    $response->assertSee('Create your account');
    $response->assertSee('Sign in');
});

test('new user can register successfully', function () {
    $response = $this->post('/register', [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
        'account_type' => 'buyer',
    ]);

    $response->assertRedirect('/');
    $this->assertDatabaseHas('users', ['email' => 'jane@example.com']);
    $this->assertAuthenticated();
});

test('registration stores a hashed password and a customer account type', function () {
    $this->post('/register', [
        'name' => 'Hash Check',
        'email' => 'hash-check@example.com',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
        'account_type' => 'buyer',
    ])->assertRedirect('/');

    $user = User::where('email', 'hash-check@example.com')->first();

    $this->assertNotNull($user);
    $this->assertNotSame('Password123!', $user->password);
    $this->assertTrue(Hash::check('Password123!', $user->password));
    $this->assertSame('buyer', $user->account_type);
});

test('duplicate email addresses are rejected', function () {
    User::factory()->create(['email' => 'taken@example.com']);

    $this->post('/register', [
        'name' => 'Second User',
        'email' => 'taken@example.com',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
        'account_type' => 'buyer',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('invalid email formats are rejected', function () {
    $this->post('/register', [
        'name' => 'Bad Email',
        'email' => 'not-an-email',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
        'account_type' => 'buyer',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('password confirmation must match', function () {
    $this->post('/register', [
        'name' => 'Mismatch User',
        'email' => 'mismatch@example.com',
        'password' => 'Password123!',
        'password_confirmation' => 'Password456!',
        'account_type' => 'buyer',
    ])->assertSessionHasErrors('password');

    $this->assertGuest();
});

test('weak passwords are rejected', function () {
    $this->post('/register', [
        'name' => 'Weak Pass',
        'email' => 'weak@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'account_type' => 'buyer',
    ])->assertSessionHasErrors('password');

    $this->assertGuest();
});

test('registration can never create an admin account', function () {
    $this->post('/register', [
        'name' => 'Evil User',
        'email' => 'evil@example.com',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
        'account_type' => 'admin',
    ])->assertSessionHasErrors('account_type');

    $this->assertDatabaseMissing('users', ['email' => 'evil@example.com']);
});
