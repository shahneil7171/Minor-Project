<?php

use App\Models\User;

test('authenticated user sees a personalized greeting on the homepage', function () {
    $user = User::factory()->create([
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
    ]);

    $this->actingAs($user)
        ->get('/')
        ->assertStatus(200)
        ->assertSee('Welcome back, Jane Doe!');
});

test('guest users see login and register prompts on the homepage', function () {
    $this->get('/')
        ->assertStatus(200)
        ->assertSee('Please log in or register to view your profile')
        ->assertSee('Log in')
        ->assertSee('Register');
});

test('authenticated user can open the dashboard page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertStatus(200)
        ->assertSee('Dashboard');
});
