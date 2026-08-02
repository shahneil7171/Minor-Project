<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\AddressController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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
    
    Route::get('/role/{role}', function ($role) {

    if (!in_array($role, ['buyer', 'seller', 'admin'])) {
        abort(404);
    }

    auth()->user()->update([
        'account_type' => $role,
    ]);

    return back()->with('success', 'Account type updated successfully.');

})->name('role.set');

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

    $getCustomProducts = function () {
        if (! Storage::disk('local')->exists('custom_products.json')) {
            return [];
        }

        $json = Storage::disk('local')->get('custom_products.json');
        if (! $json) {
            return [];
        }

        $data = json_decode($json, true);
        return is_array($data) ? $data : [];
    };

    $saveCustomProducts = function (array $customProducts) {
        Storage::disk('local')->put('custom_products.json', json_encode($customProducts, JSON_PRETTY_PRINT));
    };

    $allProducts = function () use (&$products, $getCustomProducts) {
        return array_merge($products, $getCustomProducts());
    };

    Route::get('/products', function () use ($allProducts) {
        $request = request();
        $products = $allProducts();
        
        // Extract price from price string (e.g., "$249" -> 249)
        $extractPrice = function($priceStr) {
            return (float) filter_var($priceStr, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
        };
        
        // Search filter
        $search = $request->input('search', '');
        if (!empty($search)) {
            $search = strtolower(trim($search));
            $products = array_filter($products, function($product) use ($search) {
                $title = strtolower($product['title'] ?? '');
                $description = strtolower($product['description'] ?? '');
                $subtitle = strtolower($product['subtitle'] ?? '');
                return strpos($title, $search) !== false || 
                       strpos($description, $search) !== false || 
                       strpos($subtitle, $search) !== false;
            });
        }
        
        // Sort filter
        $sort = $request->input('sort', 'none');
        if ($sort === 'price-asc') {
            uasort($products, function($a, $b) use ($extractPrice) {
                return $extractPrice($a['price']) - $extractPrice($b['price']);
            });
        } elseif ($sort === 'price-desc') {
            uasort($products, function($a, $b) use ($extractPrice) {
                return $extractPrice($b['price']) - $extractPrice($a['price']);
            });
        }
        
        return view('products', compact('products', 'search', 'sort'));
    })->name('products');

    Route::get('/products/create', function () {
        if (! in_array(auth()->user()->account_type, ['seller', 'admin'])) {
            return redirect()->route('products')->with('error', 'Only sellers or admins can add products.');
        }

        return view('add-product');
    })->name('products.create');

    Route::post('/products', function () use ($getCustomProducts, $saveCustomProducts, $products) {
        if (! in_array(auth()->user()->account_type, ['seller', 'admin'])) {
            return redirect()->route('products')->with('error', 'Only sellers or admins can add products.');
        }
        $request = request();
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'subtitle' => 'nullable|string|max:255',
            'description' => 'required|string|max:1000',
            'image' => 'nullable|url|max:1000',
            'image_file' => 'nullable|image|max:2048',
            'price' => 'required|string|max:100',
            'details' => 'nullable|string|max:1000',
        ]);

        $slug = str_replace([' ', '_'], '-', strtolower(trim($data['title'])));
        $slug = preg_replace('/[^a-z0-9\-]/', '', $slug);
        $slug = preg_replace('/\-+/', '-', $slug);
        $originalSlug = $slug;
        $customProducts = $getCustomProducts();
        $counter = 1;

        while (isset($customProducts[$slug]) || isset($products[$slug])) {
            $slug = $originalSlug . '-' . $counter++;
        }

        $details = array_values(array_filter(array_map('trim', explode("\n", $data['details'] ?? ''))));
        $image = trim($data['image'] ?: '');

        if ($request->hasFile('image_file')) {
            $uploadDir = public_path('uploads/products');
            if (! is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $file = $request->file('image_file');
            $filename = time() . '-' . Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) . '.' . $file->getClientOriginalExtension();
            $file->move($uploadDir, $filename);
            $image = '/uploads/products/' . $filename;
        }

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

        $saveCustomProducts($customProducts);

        return redirect()->route('products')->with('success', 'Product added successfully.');
    })->name('products.store');

    Route::get('/products/{product}', function ($product) use ($allProducts, $getCustomProducts) {
        $products = $allProducts();
        $customProducts = $getCustomProducts();

        if (! isset($products[$product])) {
            abort(404);
        }

        return view('product-detail', ['product' => $products[$product], 'slug' => $product, 'customProducts' => $customProducts]);
    })->name('product.show');

    Route::get('/products/{product}/edit', function ($product) use ($allProducts, $getCustomProducts) {
        $products = $allProducts();

        if (! in_array(auth()->user()->account_type, ['seller', 'admin'])) {
            return redirect()->route('products')->with('error', 'Only sellers or admins can edit products.');
        }

        if (! isset($products[$product])) {
            abort(404);
        }

        return view('edit-product', ['product' => $products[$product], 'slug' => $product]);
    })->name('products.edit');

    Route::post('/products/{product}/update', function ($product) use ($allProducts, $getCustomProducts, $saveCustomProducts) {
        if (! in_array(auth()->user()->account_type, ['seller', 'admin'])) {
            return redirect()->route('products')->with('error', 'Only sellers or admins can update products.');
        }

        $allProds = $allProducts();
        if (! isset($allProds[$product])) {
            abort(404);
        }

        $request = request();
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'subtitle' => 'nullable|string|max:255',
            'description' => 'required|string|max:1000',
            'image' => 'nullable|url|max:1000',
            'image_file' => 'nullable|image|max:2048',
            'price' => 'required|string|max:100',
            'details' => 'nullable|string|max:1000',
        ]);

        $customProducts = $getCustomProducts();
        $details = array_values(array_filter(array_map('trim', explode("\n", $data['details'] ?? ''))));
        $image = trim($data['image'] ?? '');

        if ($request->hasFile('image_file')) {
            $uploadDir = public_path('uploads/products');
            if (! is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $file = $request->file('image_file');
            $filename = time() . '-' . Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) . '.' . $file->getClientOriginalExtension();
            $file->move($uploadDir, $filename);
            $image = '/uploads/products/' . $filename;
        } elseif (empty($image)) {
            // If no new image uploaded and no URL provided, keep the existing image
            $image = isset($allProds[$product]['image']) ? $allProds[$product]['image'] : '';
        }

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

        $saveCustomProducts($customProducts);

        return redirect()->route('products')->with('success', 'Product updated successfully.');
    })->name('products.update');

    Route::post('/products/{product}/delete', function ($product) use ($getCustomProducts, $saveCustomProducts) {
        if (! in_array(auth()->user()->account_type, ['seller', 'admin'])) {
            return redirect()->route('products')->with('error', 'Only sellers or admins can remove products.');
        }

        $customProducts = $getCustomProducts();

        if (! isset($customProducts[$product])) {
            abort(404);
        }

        unset($customProducts[$product]);
        $saveCustomProducts($customProducts);

        $cart = session()->get('cart', []);
        if (isset($cart[$product])) {
            unset($cart[$product]);
            session(['cart' => $cart]);
        }

        return redirect()->route('products')->with('success', 'Product removed successfully.');
    })->name('products.destroy');

    Route::post('/cart/add/{product}', function ($product) use ($allProducts) {
        if (auth()->user()->account_type === 'seller') {
            return redirect()->route('products')->with('error', 'Sellers cannot add items to cart.');
        }
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
        if (auth()->user()->account_type === 'seller') {
            return redirect()->route('products')->with('error', 'Sellers cannot purchase items.');
        }
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

    Route::post('/cart/buy-now-item/{product}', function ($product) use ($allProducts) {
        if (auth()->user()->account_type === 'seller') {
            return redirect()->route('products')->with('error', 'Sellers cannot purchase items.');
        }

        $products = $allProducts();
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
        $shippingAddress = Auth::user()->defaultShippingAddress;

        foreach ($cart as $item) {
            $price = floatval(str_replace(['$', ','], '', $item['price']));
            $total += $price * $item['quantity'];
        }

        return view('checkout', [
            'cart' => $cart,
            'total' => $total,
            'shippingAddress' => $shippingAddress,
        ]);
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
