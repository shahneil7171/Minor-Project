<?php

use App\Models\User;

test('authenticated users can browse the homepage', function () {
    $user = User::factory()->create([
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
    ]);

    $this->actingAs($user)
        ->get('/')
        ->assertStatus(200)
        ->assertSee('KDP MART')
        ->assertSee('Featured Products')
        ->assertSee('Best Sellers');
});

test('authenticated user can open the dashboard page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertStatus(200)
        ->assertSee('Dashboard');
});
