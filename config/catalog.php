<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Seed Product Catalog
    |--------------------------------------------------------------------------
    | The built-in base catalog ships with the store. Admin/seller created
    | products are stored separately in custom_products.json. This file is the
    | single source of truth for the seed products so both the route closures
    | and the ProductCatalogService can share the same data.
    */
    'seed_products' => [
        'smart-watch-pro' => [
            'title'          => 'Smart Watch Pro',
            'sku'            => 'KDP-SMW-001',
            'subtitle'       => 'Track wellness, stay connected, and charge quickly for all-day wear.',
            'description'    => 'A polished companion for fitness, notifications, and every active lifestyle.',
            'image'          => 'https://images.unsplash.com/photo-1518444209757-9ae0b9eb3734?auto=format&fit=crop&w=800&q=80',
            'images'         => [
                'https://images.unsplash.com/photo-1518444209757-9ae0b9eb3734?auto=format&fit=crop&w=800&q=80',
            ],
            'details'        => [
                'Heart rate monitoring',
                'GPS built-in',
                'Sleep analysis',
                'Long battery life',
                'Water resistant',
            ],
            'price'         => 249,
            'special_price' => 199,
            'quantity'      => 12,
            'stock_status'  => 'in-stock',
            'category'      => 'Electronics',
            'subcategory'   => 'Accessories',
            'brand'         => 'KDP Tech',
            'tax'           => 18,
            'status'        => 1,
            'slug'          => 'smart-watch-pro',
            'tags'          => ['wearables', 'smartwatch', 'fitness', 'gps'],
        ],
        'signature-headphones' => [
            'title'          => 'Signature Headphones',
            'sku'            => 'SL-HP-001',
            'subtitle'       => 'Immersive audio with studio-grade clarity and premium noise isolation.',
            'description'    => 'Delivers studio-grade sound and a comfortable fit for long listening sessions.',
            'image'          => 'https://images.unsplash.com/photo-1516574187841-cb9cc2ca948b?auto=format&fit=crop&w=800&q=80',
            'images'         => [
                'https://images.unsplash.com/photo-1516574187841-cb9cc2ca948b?auto=format&fit=crop&w=800&q=80',
            ],
            'details'        => [
                'Active noise cancellation',
                'Wireless Bluetooth connection',
                'Long battery life',
                'Touch controls',
                'Fast charging',
            ],
            'price'         => 179,
            'special_price' => 149,
            'quantity'      => 8,
            'stock_status'  => 'in-stock',
            'category'      => 'Electronics',
            'subcategory'   => 'Accessories',
            'brand'         => 'SonicLabs',
            'tax'           => 18,
            'status'        => 1,
            'slug'          => 'signature-headphones',
            'tags'          => ['audio', 'headphones', 'wireless', 'anc'],
        ],
        'premium-backpack' => [
            'title'          => 'Premium Backpack',
            'sku'            => 'TP-BP-002',
            'subtitle'       => 'Travel-ready design with durable storage and sleek modern styling.',
            'description'    => 'Built for everyday commutes and weekend adventures with premium organization.',
            'image'          => 'https://images.unsplash.com/photo-1512436991641-6745cdb1723f?auto=format&fit=crop&w=800&q=80',
            'images'         => [
                'https://images.unsplash.com/photo-1512436991641-6745cdb1723f?auto=format&fit=crop&w=800&q=80',
            ],
            'details'        => [
                'Padded laptop compartment',
                'Water-resistant fabric',
                'Multiple pockets',
                'Ergonomic straps',
                'Lightweight build',
            ],
            'price'         => 129,
            'special_price' => 99,
            'quantity'      => 25,
            'stock_status'  => 'in-stock',
            'category'      => 'Fashion',
            'subcategory'   => 'Men',
            'brand'         => 'TravelPro',
            'tax'           => 0,
            'status'        => 1,
            'slug'          => 'premium-backpack',
            'tags'          => ['backpack', 'travel', 'laptop', 'everyday'],
        ],
    ],

];