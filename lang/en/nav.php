<?php

/*
|--------------------------------------------------------------------------
| Navigation chrome
|--------------------------------------------------------------------------
| Group file: lang/{locale}/nav.php resolves __('nav.*') because Laravel's
| FileLoader loads lang/{locale}/{group}.php for the group "nav". A flat
| lang/{locale}.php file is NEVER read for dotted keys — that was the reason
| raw keys such as "nav.home" were shown to customers.
|
| Placeholder syntax is Laravel's: __('nav.key', ['name' => 'value']).
*/

return [
    'home'               => 'Home',
    'categories'         => 'Categories',
    'products'           => 'Products',
    'deals'              => 'Deals',
    'about'              => 'About Us',
    'contact'            => 'Contact',
    'search_placeholder' => 'Search for products...',
    'sales'              => 'Sales',
    'customers'          => 'Customers',
    'orders'             => 'Orders',
    'reviews'            => 'Reviews',
    'my_orders'          => 'My Orders',
    'my_dashboard'       => 'My Dashboard',
    'my_profile'         => 'My Profile',
    'cart'               => 'Cart',
    'wishlist'           => 'Wishlist',
    'logout'             => 'Logout',
    'login'              => 'Login',
    'register'           => 'Register',
    'admin_panel'        => 'Admin Panel',
];
