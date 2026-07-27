<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\AddressController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (Auth::check()) {
        return view('dashboard');
    }

    return view('splash');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.post');
    Route::get('/forgot-password', [AuthController::class, 'showForgotPasswordForm'])->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'sendOtp'])->name('password.email');
    Route::get('/forgot-password/verify', [AuthController::class, 'showVerifyOtpForm'])->name('password.verify');
    Route::post('/forgot-password/verify', [AuthController::class, 'verifyOtp'])->name('password.verify.post');
    Route::get('/reset-password', [AuthController::class, 'showResetForm'])->name('password.reset');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('password.update');
    Route::get('/register', [AuthController::class, 'showRegisterForm'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->name('register.post');
});

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');

    Route::get('/home', function () {
        return redirect()->route('dashboard');
    })->name('home');

    $products = [
        'smart-watch-pro' => [
            'title' => 'Smart Watch Pro',
            'subtitle' => 'Track wellness, stay connected, and charge quickly for all-day wear.',
            'description' => 'A polished companion for fitness, notifications, and every active lifestyle.',
            'image' => 'https://images.unsplash.com/photo-1518444209757-9ae0b9eb3734?auto=format&fit=crop&w=800&q=80',
            'details' => [
                'Heart rate monitoring',
                'GPS built-in',
                'Sleep analysis',
                'Long battery life',
                'Water resistant',
            ],
            'price' => '$249',
        ],
        'signature-headphones' => [
            'title' => 'Signature Headphones',
            'subtitle' => 'Immersive audio with studio-grade clarity and premium noise isolation.',
            'description' => 'Delivers studio-grade sound and a comfortable fit for long listening sessions.',
            'image' => 'https://images.unsplash.com/photo-1516574187841-cb9cc2ca948b?auto=format&fit=crop&w=800&q=80',
            'details' => [
                'Active noise cancellation',
                'Wireless Bluetooth connection',
                'Long battery life',
                'Touch controls',
                'Fast charging',
            ],
            'price' => '$179',
        ],
        'premium-backpack' => [
            'title' => 'Premium Backpack',
            'subtitle' => 'Travel-ready design with durable storage and sleek modern styling.',
            'description' => 'Built for everyday commutes and weekend adventures with premium organization.',
            'image' => 'https://images.unsplash.com/photo-1512436991641-6745cdb1723f?auto=format&fit=crop&w=800&q=80',
            'details' => [
                'Padded laptop compartment',
                'Water-resistant fabric',
                'Multiple pockets',
                'Ergonomic straps',
                'Lightweight build',
            ],
            'price' => '$129',
        ],
    ];

    $allProducts = function () use (&$products) {
        return array_merge($products, session('custom_products', []));
    };

    Route::get('/products', function () use ($allProducts) {
        $products = $allProducts();
        return view('products', compact('products'));
    })->name('products');

    Route::get('/products/create', function () {
        return view('add-product');
    })->name('products.create');

    Route::post('/products', function () {
        $data = request()->validate([
            'title' => 'required|string|max:255',
            'subtitle' => 'nullable|string|max:255',
            'description' => 'required|string|max:1000',
            'image' => 'nullable|url|max:1000',
            'price' => 'required|string|max:100',
            'details' => 'nullable|string|max:1000',
        ]);

        $slug = 
            
            
            str_replace([' ', '_'], '-', strtolower(trim($data['title'])));
        $slug = preg_replace('/[^a-z0-9\-]/', '', $slug);
        $slug = preg_replace('/\-+/', '-', $slug);
        $originalSlug = $slug;
        $customProducts = session('custom_products', []);
        $counter = 1;

        while (isset($customProducts[$slug]) || isset($products[$slug])) {
            $slug = $originalSlug . '-' . $counter++;
        }

        $details = array_values(array_filter(array_map('trim', explode("\n", $data['details'] ?? ''))));
        $image = trim($data['image'] ?: '');
        if ($image === '') {
            $image = 'https://images.unsplash.com/photo-1512436991641-6745cdb1723f?auto=format&fit=crop&w=800&q=80';
        }

        $customProducts[$slug] = [
            'title' => $data['title'],
            'subtitle' => $data['subtitle'] ?: 'No subtitle provided.',
            'description' => $data['description'],
            'image' => $image,
            'details' => $details ?: ['No additional details provided.'],
            'price' => strpos(trim($data['price']), '$') === 0 ? trim($data['price']) : '$' . trim($data['price']),
        ];

        session(['custom_products' => $customProducts]);

        return redirect()->route('products')->with('success', 'Product added successfully.');
    })->name('products.store');

    Route::get('/products/{product}', function ($product) use ($allProducts) {
        $products = $allProducts();

        if (! isset($products[$product])) {
            abort(404);
        }

        return view('product-detail', ['product' => $products[$product], 'slug' => $product]);
    })->name('product.show');

    Route::get('/products/{product}/edit', function ($product) use ($allProducts) {
        $products = $allProducts();

        if (! isset($products[$product]) || ! isset(session('custom_products', [])[$product])) {
            abort(404);
        }

        return view('edit-product', ['product' => $products[$product], 'slug' => $product]);
    })->name('products.edit');

    Route::post('/products/{product}/update', function ($product) use ($allProducts) {
        $customProducts = session('custom_products', []);

        if (! isset($customProducts[$product])) {
            abort(404);
        }

        $data = request()->validate([
            'title' => 'required|string|max:255',
            'subtitle' => 'nullable|string|max:255',
            'description' => 'required|string|max:1000',
            'image' => 'nullable|url|max:1000',
            'price' => 'required|string|max:100',
            'details' => 'nullable|string|max:1000',
        ]);

        $details = array_values(array_filter(array_map('trim', explode("\n", $data['details'] ?? ''))));
        $image = trim($data['image'] ?: '');
        if ($image === '') {
            $image = 'https://images.unsplash.com/photo-1512436991641-6745cdb1723f?auto=format&fit=crop&w=800&q=80';
        }

        $customProducts[$product] = [
            'title' => $data['title'],
            'subtitle' => $data['subtitle'] ?: 'No subtitle provided.',
            'description' => $data['description'],
            'image' => $image,
            'details' => $details ?: ['No additional details provided.'],
            'price' => strpos(trim($data['price']), '$') === 0 ? trim($data['price']) : '$' . trim($data['price']),
        ];

        session(['custom_products' => $customProducts]);

        return redirect()->route('products')->with('success', 'Product updated successfully.');
    })->name('products.update');

    Route::post('/products/{product}/delete', function ($product) {
        $customProducts = session('custom_products', []);

        if (! isset($customProducts[$product])) {
            abort(404);
        }

        unset($customProducts[$product]);
        session(['custom_products' => $customProducts]);

        $cart = session()->get('cart', []);
        if (isset($cart[$product])) {
            unset($cart[$product]);
            session(['cart' => $cart]);
        }

        return redirect()->route('products')->with('success', 'Product removed successfully.');
    })->name('products.destroy');

    Route::post('/cart/add/{product}', function ($product) use ($allProducts) {
        $products = $allProducts();

        if (!isset($products[$product])) {
            abort(404);
        }

        $cart = session()->get('cart', []);

        if (isset($cart[$product])) {
            $cart[$product]['quantity']++;
        } else {
            $cart[$product] = [
                'title' => $products[$product]['title'],
                'price' => $products[$product]['price'],
                'quantity' => 1,
            ];
        }

        session(['cart' => $cart]);

        return redirect()->route('cart.index')
            ->with('success', 'Product added to cart!');
    })->name('cart.add');

    Route::post('/cart/buy-now/{product}', function ($product) use ($allProducts) {
        $products = $allProducts();
        if (! isset($products[$product])) {
            abort(404);
        }

        $data = request()->validate([
            'quantity' => 'required|integer|min:1',
        ]);

        $quantity = (int) $data['quantity'];
        $cart = session()->get('cart', []);
        $cart[$product] = [
            'title' => $products[$product]['title'],
            'price' => $products[$product]['price'],
            'quantity' => $quantity,
        ];
        session(['cart' => $cart, 'buy_now' => $product]);

        return redirect()->route('checkout.review');
    })->name('cart.buy-now');

    Route::get('/checkout/review', function () {
        $cart = session()->get('cart', []);
        $buyNow = session()->get('buy_now_item');

        if ($buyNow) {
            if (! isset($cart[$buyNow['product']])) {
                return redirect()->route('cart.index');
            }

            $cart = [
                $buyNow['product'] => [
                    'title' => $cart[$buyNow['product']]['title'],
                    'price' => $cart[$buyNow['product']]['price'],
                    'quantity' => $buyNow['quantity'],
                ],
            ];
        }

        $total = 0;

        foreach ($cart as $item) {
            $price = floatval(str_replace(['$', ','], '', $item['price']));
            $total += $price * $item['quantity'];
        }

        return view('checkout-review', ['cart' => $cart, 'total' => $total]);
    })->name('checkout.review');

    Route::get('/cart', function () {
        $cart = session()->get('cart', []);
        return view('cart', ['cart' => $cart]);
    })->name('cart.index');

    Route::post('/cart/remove/{product}', function ($product) {
        $cart = session()->get('cart', []);

        if (isset($cart[$product])) {
            unset($cart[$product]);
            session(['cart' => $cart]);
        }

        return redirect()->route('cart.index');
    })->name('cart.remove');

    Route::post('/cart/increase/{product}', function ($product) {
        $cart = session()->get('cart', []);

        if (isset($cart[$product])) {
            $cart[$product]['quantity'] += 1;
            session(['cart' => $cart]);
        }

        return redirect()->route('cart.index');
    })->name('cart.increase');

    Route::post('/cart/decrease/{product}', function ($product) {
        $cart = session()->get('cart', []);

        if (isset($cart[$product])) {
            if ($cart[$product]['quantity'] > 1) {
                $cart[$product]['quantity'] -= 1;
            } else {
                unset($cart[$product]);
            }
            session(['cart' => $cart]);
        }

        return redirect()->route('cart.index');
    })->name('cart.decrease');

    Route::post('/cart/buy-now-item/{product}', function ($product) use ($products) {
        if (! isset($products[$product])) {
            abort(404);
        }

        $cart = session()->get('cart', []);
        if (! isset($cart[$product])) {
            return redirect()->route('cart.index');
        }

        $data = request()->validate([
            'quantity' => 'required|integer|min:1',
        ]);

        session(['buy_now_item' => [
            'product' => $product,
            'quantity' => (int) $data['quantity'],
        ]]);

        return redirect()->route('checkout.review');
    })->name('cart.buy-now-item');

    Route::get('/checkout', function () {
        $cart = session()->get('cart', []);
        $total = 0;

        foreach ($cart as $item) {
            $price = floatval(str_replace(['$', ','], '', $item['price']));
            $total += $price * $item['quantity'];
        }

        return view('checkout', ['cart' => $cart, 'total' => $total]);
    })->name('checkout.index');

    Route::post('/checkout', function () {
        $data = request()->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'address' => 'required|string|max:500',
            'city' => 'required|string|max:100',
            'state' => 'required|string|max:100',
            'pincode' => 'required|string|max:20',
        ]);

        $cart = session()->get('cart', []);
        $buyNow = session()->get('buy_now_item');
        $order = $cart;

        if ($buyNow) {
            $product = $buyNow['product'];

            if (! isset($cart[$product])) {
                return redirect()->route('cart.index');
            }

            $order = [
                $product => [
                    'title' => $cart[$product]['title'],
                    'price' => $cart[$product]['price'],
                    'quantity' => $buyNow['quantity'],
                ],
            ];

            unset($cart[$product]);
            session(['cart' => $cart]);
            session()->forget('buy_now_item');
        } else {
            session()->forget('cart');
        }

        session(['order' => $order, 'checkout' => $data]);

        return redirect()->route('checkout.complete');
    })->name('checkout.submit');

    Route::get('/checkout/complete', function () {
        $cart = session()->get('order', []);
        $checkout = session()->get('checkout', []);

        if (empty($cart) || empty($checkout)) {
            return redirect()->route('cart.index');
        }

        return view('checkout-complete', ['cart' => $cart, 'checkout' => $checkout]);
    })->name('checkout.complete');

    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Profile Routes
    Route::prefix('/profile')->name('profile.')->group(function () {
        // Profile Display and Edit
        Route::get('/', [ProfileController::class, 'show'])->name('show');
        Route::get('/edit', [ProfileController::class, 'edit'])->name('edit');
        Route::post('/update', [ProfileController::class, 'update'])->name('update');
        Route::delete('/photo', [ProfileController::class, 'deletePhoto'])->name('deletePhoto');

        // Change Password
        Route::get('/change-password', [ProfileController::class, 'showChangePassword'])->name('change-password');
        Route::post('/change-password', [ProfileController::class, 'updatePassword'])->name('update-password');

        // Address Management
        Route::prefix('/addresses')->name('addresses.')->group(function () {
            Route::get('/', [AddressController::class, 'index'])->name('index');
            Route::get('/create', [AddressController::class, 'create'])->name('create');
            Route::post('/', [AddressController::class, 'store'])->name('store');
            Route::get('/{address}/edit', [AddressController::class, 'edit'])->name('edit');
            Route::patch('/{address}', [AddressController::class, 'update'])->name('update');
            Route::delete('/{address}', [AddressController::class, 'destroy'])->name('destroy');
            Route::post('/{address}/set-default-shipping', [AddressController::class, 'setDefaultShipping'])->name('set-default-shipping');
            Route::post('/{address}/set-default-billing', [AddressController::class, 'setDefaultBilling'])->name('set-default-billing');
        });
    });
});
