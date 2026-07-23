<?php

test('registration page is available', function () {
    $response = $this->get('/register');

    $response->assertStatus(200);
    $response->assertSee('Create your account');
    $response->assertSee('Create one');
});

test('new user can register successfully', function () {
    $response = $this->post('/register', [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertRedirect('/');
    $this->assertDatabaseHas('users', ['email' => 'jane@example.com']);
    $this->assertAuthenticated();
});
