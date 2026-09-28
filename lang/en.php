<?php

/**
 * PHASE 3 — English source strings.
 *
 * These are the canonical values: every other locale file must return the same
 * key set (Laravel falls back to `en` for a missing key automatically, so a
 * partial translation can never blank out a label).
 *
 * Strings are namespaced by area. Placeholder syntax is Laravel's:
 *   __('key', ['name' => 'value'])  ->  ":name" in the string.
 */
return [

    // --- Display preferences (switcher UI + its flash messages) -----------
    'preferences' => [
        'language'          => 'Language',
        'currency'          => 'Currency',
        'reset'             => 'Display preferences reset to defaults.',
        'reset_action'      => 'Reset',
        'currency_set'      => 'Prices now shown in :currency.',
        'locale_set'        => 'Language changed to :locale.',
        'unknown_currency'  => 'That currency is not available.',
        'unknown_locale'    => 'That language is not available.',
    ],

    // --- Primary navigation / chrome ---------------------------------------
    'nav' => [
        'home'              => 'Home',
        'products'          => 'Products',
        'deals'             => 'Deals',
        'about'             => 'About Us',
        'contact'           => 'Contact',
        'search_placeholder'=> 'Search for products...',
        'sales'             => 'Sales',
        'customers'         => 'Customers',
        'orders'            => 'Orders',
        'reviews'           => 'Reviews',
        'my_orders'         => 'My Orders',
        'my_dashboard'      => 'My Dashboard',
        'my_profile'        => 'My Profile',
        'cart'              => 'Cart',
        'wishlist'          => 'Wishlist',
        'logout'            => 'Logout',
        'login'             => 'Login',
        'register'          => 'Register',
        'admin_panel'       => 'Admin Panel',
    ],

    // --- Storefront ---------------------------------------------------------
    'product' => [
        'price'             => 'Price',
        'add_to_cart'       => 'Add to Cart',
        'buy_now'           => 'Buy Now',
        'in_stock'          => 'In Stock',
        'out_of_stock'      => 'Out of Stock',
        'save'              => 'Save',
        'reviews'           => 'Reviews',
    ],

    'cart' => [
        'title'             => 'Shopping Cart',
        'subtotal'          => 'Subtotal',
        'total'             => 'Total',
        'checkout'          => 'Proceed to Checkout',
        'empty'             => 'Your cart is empty.',
        'quantity'          => 'Quantity',
    ],

    'checkout' => [
        'title'             => 'Checkout',
        'shipping'          => 'Shipping',
        'tax'               => 'Tax',
        'discount'          => 'Discount',
        'coupon'            => 'Coupon',
        'place_order'       => 'Place Order',
    ],

    'order' => [
        'title'             => 'My Orders',
        'status'            => 'Status',
        'total'             => 'Total',
        'subtotal'          => 'Subtotal',
        'items'             => 'Items',
        'date'              => 'Date',
    ],
];
