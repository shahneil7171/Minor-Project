<?php

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

test('guests are redirected to the login page when trying to view orders', function () {
    $response = $this->get('/my-orders');

    $response->assertRedirect('/login');
});

test('authenticated users can view their own orders', function () {
    $user = User::factory()->create();
    $order = Order::create([
        'user_id' => $user->id,
        'order_number' => 'ORD-1001',
        'total_amount' => 149.99,
        'payment_status' => 'Paid',
        'order_status' => 'Processing',
        'shipping_address' => '123 Market Street',
        'payment_method' => 'Stripe',
        'billing_address' => '123 Market Street',
    ]);

    $response = $this->actingAs($user)->get('/my-orders');

    $response->assertOk();
    $response->assertSee($order->order_number);
});

test('users cannot view another users order details', function () {
    $owner = User::factory()->create();
    $viewer = User::factory()->create();
    $order = Order::create([
        'user_id' => $owner->id,
        'order_number' => 'ORD-1002',
        'total_amount' => 89.5,
        'payment_status' => 'Pending',
        'order_status' => 'Pending',
        'shipping_address' => '456 Oak Avenue',
        'payment_method' => 'COD',
        'billing_address' => '456 Oak Avenue',
    ]);

    $response = $this->actingAs($viewer)->get('/my-orders/' . $order->id);

    $response->assertForbidden();
});
