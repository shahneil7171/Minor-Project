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

    Route::get('/products', function () use ($products) {
        return view('products', compact('products'));
    })->name('products');

    Route::get('/products/{product}', function ($product) use ($products) {
        if (! isset($products[$product])) {
            abort(404);
        }

        return view('product-detail', ['product' => $products[$product], 'slug' => $product]);
    })->name('product.show');

    Route::post('/cart/add/{product}', function ($product) use ($products) {
        if (! isset($products[$product])) {
            abort(404);
        }

        $cart = session()->get('cart', []);

        if (isset($cart[$product])) {
            $cart[$product]['quantity'] += 1;
        } else {
            $cart[$product] = [
                'title' => $products[$product]['title'],
                'price' => $products[$product]['price'],
                'quantity' => 1,
            ];
        }

        session(['cart' => $cart]);

        return redirect()->route('product.show', ['product' => $product])
            ->with('success', 'Product added to your cart.');
    })->name('cart.add');

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

        session(['checkout' => $data]);

        return redirect()->route('checkout.complete');
    })->name('checkout.submit');

    Route::get('/checkout/complete', function () {
        $cart = session()->get('cart', []);
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
