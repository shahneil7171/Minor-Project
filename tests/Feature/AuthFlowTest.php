<?php

use App\Models\User;

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
