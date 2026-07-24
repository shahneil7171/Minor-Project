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

test('authenticated user can open the dashboard page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertStatus(200)
        ->assertSee('Dashboard');
});
