<?php

test('login page is available', function () {
    $response = $this->get('/login');

    $response->assertStatus(200);
    $response->assertSee('Welcome back');
    $response->assertSee('Sign in to your account');
});
