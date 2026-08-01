<?php

use App\Models\User;

it('shows a file upload field on the add product form', function () {
    $user = new User([
        'id' => 1,
        'name' => 'Seller',
        'email' => 'seller@example.com',
    ]);

    $response = $this->actingAs($user, 'web')
        ->withSession(['role' => 'seller'])
        ->get('/products/create');

    $response->assertOk();
    $response->assertSee('image_file');
    $response->assertSee('accept="image/*"', false);
});

it('renders uploaded product images on the products page', function () {
    $html = view('products', [
        'products' => [
            'demo-product' => [
                'title' => 'Demo Product',
                'subtitle' => 'A sample product',
                'description' => 'Demo description',
                'image' => '/uploads/products/demo.jpg',
                'details' => ['Detail'],
                'price' => '$19',
            ],
        ],
        'customProducts' => [],
    ])->render();

    expect($html)->toContain('/uploads/products/demo.jpg');
});
