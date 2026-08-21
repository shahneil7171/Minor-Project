<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\AddressController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\ReviewsController;
use App\Http\Controllers\WishlistController;
use App\Http\Controllers\OrdersController;
use App\Http\Controllers\AdminOrdersController;
use App\Http\Controllers\AdminCustomersController;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\AdminCategoriesController;
use App\Http\Controllers\AdminManufacturersController;
use App\Http\Controllers\AdminOptionsController;
use App\Http\Controllers\AdminReturnsController;
use App\Http\Controllers\AdminPromotionsController;
use App\Http\Controllers\AdminNewsletterController;
use App\Http\Controllers\AdminReportsController;
use App\Http\Controllers\AdminSystemUsersController;
use App\Http\Controllers\AdminUserGroupsController;
use App\Http\Controllers\AdminSettingsController;
use App\Http\Controllers\AdminBackupController;
use App\Http\Controllers\CouponsController;
use App\Models\WishlistItem;
use App\Services\ProductVariantService;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| Shared Product Catalog
|--------------------------------------------------------------------------
| The base seed catalog plus admin/seller added products (stored in
| custom_products.json) are shared by the public store home and the
| authenticated product routes below.
*/
$seedProducts = config('catalog.seed_products');

$getCustomProducts = function () {
    return app(\App\Services\ProductCatalogService::class)->customProducts();
};

$saveCustomProducts = function (array $customProducts) {
    app(\App\Services\ProductCatalogService::class)->saveCustomProducts($customProducts);
};

$allProducts = function () use (&$seedProducts) {
    return app(\App\Services\ProductCatalogService::class)->all();
};

// Convert a stored price (number or "$X"-style string) into a clean float.
$priceFloat = function ($price) {
    return (float) filter_var((string) ($price ?? 0), FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
};

// Effective sale price: respects "special_price" when it is lower than the base price.
$priceOf = function ($product) use ($priceFloat) {
    $base  = $priceFloat($product['price'] ?? 0);
    $special = isset($product['special_price']) && $product['special_price'] !== ''
        ? $priceFloat($product['special_price']) : 0;

    return ($special > 0 && $special < $base) ? $special : $base;
};

/*
|--------------------------------------------------------------------------
| Store Home
|--------------------------------------------------------------------------
| A public storefront every visitor can browse. Logged-in shoppers get
| working cart / wishlist actions, guests are guided to sign in.
*/
Route::get('/', function () use ($allProducts) {
    // Only show products whose "status" flag is enabled (OpenCart-style status).
    $all = array_values(array_filter($allProducts(), function ($p) {
        return ! isset($p['status']) || (int) $p['status'] === 1;
    }));

    $featured = array_slice($all, 0, 4);
    $bestSellers = array_slice($all, 4, 4);
    $specialOffers = array_slice($all, 0, 4);

    $cartCount = array_sum(array_column(session('cart', []), 'quantity'));

    $wishlistSlugs = auth()->check()
        ? WishlistItem::where('user_id', auth()->id())->pluck('product_slug')->all()
        : [];
    $wishlistCount = count($wishlistSlugs);

    // Active promotional banners from the admin Marketing module.
    $promotions = \App\Models\Promotion::active()->get();

    return view('home', compact('featured', 'bestSellers', 'specialOffers', 'cartCount', 'wishlistCount', 'wishlistSlugs', 'promotions'));
})->name('home');

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

Route::get('/cart', [CheckoutController::class, 'cart'])->name('cart.index');
Route::post('/cart/add/{product}', [CheckoutController::class, 'addToCart'])->name('cart.add');
Route::post('/cart/buy-now/{product}', [CheckoutController::class, 'buyNow'])->name('cart.buy-now');
Route::post('/cart/remove/{product}', [CheckoutController::class, 'removeCartItem'])->name('cart.remove');
Route::post('/cart/increase/{product}', [CheckoutController::class, 'increaseCartItem'])->name('cart.increase');
Route::post('/cart/decrease/{product}', [CheckoutController::class, 'decreaseCartItem'])->name('cart.decrease');
Route::post('/cart/buy-now-item/{product}', [CheckoutController::class, 'buyNowCartItem'])->name('cart.buy-now-item');

Route::get('/checkout/review', [CheckoutController::class, 'review'])->name('checkout.review');
Route::get('/checkout', [CheckoutController::class, 'index'])->name('checkout.index');
Route::post('/checkout/coupon', [CheckoutController::class, 'applyCoupon'])->name('checkout.coupon.apply');
Route::delete('/checkout/coupon', [CheckoutController::class, 'removeCoupon'])->name('checkout.coupon.remove');
Route::post('/checkout', [CheckoutController::class, 'submit'])->name('checkout.submit');
Route::get('/checkout/complete', [CheckoutController::class, 'complete'])->name('checkout.complete');

Route::middleware('auth')->group(function () use ($allProducts, $getCustomProducts, $saveCustomProducts, $seedProducts, $priceOf, $priceFloat) {
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');

    Route::get('/home', function () {
        return redirect()->route('home');
    });
    
    Route::get('/role/{role}', function ($role) {

    if (!in_array($role, ['buyer', 'seller', 'admin'])) {
        abort(404);
    }

    auth()->user()->update([
        'account_type' => $role,
    ]);

    return back()->with('success', 'Account type updated successfully.');

})->name('role.set');

    Route::get('/products', function () use ($allProducts, $priceOf, $priceFloat) {
        $request = request();
        $products = $allProducts();
        $userRole = auth()->user()->account_type;
        $categories = \App\Models\Category::all();

        // Buyers should not see disabled (status = 0) products; sellers/admins manage all.
        if ($userRole === 'buyer') {
            $products = array_filter($products, function ($p) {
                return ! isset($p['status']) || (int) $p['status'] === 1;
            });
        }

        // -------------------------------------------------------------
        //  SEARCH
        //  Case-insensitive, partial (substring) matching across the
        //  catalog fields: title, brand, category, subcategory, SKU and
        //  tags (plus subtitle/description for helpful context). Every
        //  search term must match (AND semantics) so multi-word queries
        //  stay precise, e.g. "samsung galaxy" needs both words.
        //
        //  NOTE: this project stores its product catalog in the JSON-backed
        //  product store (seed catalog + custom_products.json) — there is no
        //  products SQL table in this codebase — so the filter runs over the
        //  loaded product arrays with safe string operations (no raw SQL).
        // -------------------------------------------------------------
        $search = trim((string) $request->input('search', ''));

        if ($search !== '') {
            // Bound the query length so oversized inputs cannot hurt the page.
            $search = mb_substr($search, 0, 120);

            $terms = array_values(array_filter(array_map(
                fn ($term) => mb_strtolower(trim($term, " \t\n\r\0\x0B\"'")),
                preg_split('/[\s,]+/u', $search)
            )));

            if (count($terms) > 0) {
                $products = array_filter($products, function ($product) use ($terms) {
                    $haystack = mb_strtolower(implode(' ', array_filter([
                        $product['title'] ?? '',
                        $product['brand'] ?? '',
                        $product['category'] ?? '',
                        $product['subcategory'] ?? '',
                        $product['sku'] ?? '',
                        is_array($product['tags'] ?? null)
                            ? implode(' ', $product['tags'])
                            : ($product['tags'] ?? ''),
                        $product['subtitle'] ?? '',
                        $product['description'] ?? '',
                    ])));

                    foreach ($terms as $term) {
                        if (mb_strpos($haystack, $term) === false) {
                            return false;
                        }
                    }

                    return true;
                });
            }
        }

        // Category filter (combines with the search above and the sort below)
        $category = trim((string) $request->input('category', ''));
        if ($category !== '') {
            $categoryKey = mb_strtolower($category);
            $products = array_filter($products, function ($product) use ($categoryKey) {
                return mb_strtolower($product['category'] ?? '') === $categoryKey;
            });
        }

        // Brand filter
        $brand = trim((string) $request->input('brand', ''));
        if ($brand !== '') {
            $brandKey = mb_strtolower($brand);
            $products = array_filter($products, function ($product) use ($brandKey) {
                return mb_strtolower($product['brand'] ?? '') === $brandKey;
            });
        }

        // Sort filter (uses effective/special price)
        $sort = $request->input('sort', 'none');
        if ($sort === 'price-asc' || $sort === 'price-desc') {
            uasort($products, function ($a, $b) use ($priceOf, $sort) {
                return $sort === 'price-asc'
                    ? $priceOf($a) <=> $priceOf($b)
                    : $priceOf($b) <=> $priceOf($a);
            });
        }

        // Price range filter with validation
        $minPrice = trim((string) $request->input('min_price', ''));
        $maxPrice = trim((string) $request->input('max_price', ''));

        // Validate price inputs
        $minPriceValid = false;
        $maxPriceValid = false;
        $minPriceVal = 0;
        $maxPriceVal = 0;

        if ($minPrice !== '') {
            $minPriceVal = (float) $minPrice;
            $minPriceValid = $minPriceVal >= 0;
        }

        if ($maxPrice !== '') {
            $maxPriceVal = (float) $maxPrice;
            $maxPriceValid = $maxPriceVal >= 0;
        }

        if ($minPriceValid && $maxPriceValid && $minPriceVal <= $maxPriceVal) {
            $products = array_filter($products, function ($product) use ($minPriceVal, $maxPriceVal) {
                $price = (float) ($product['price'] ?? 0);
                return $price >= $minPriceVal && $price <= $maxPriceVal;
            });
        }

        // Availability filter
        $availability = trim((string) $request->input('availability', ''));
        if ($availability === 'in-stock') {
            $products = array_filter($products, function ($product) {
                $stock = (int) ($product['stock'] ?? 0);
                $stock_status = $product['stock_status'] ?? 'in-stock';
                return $stock > 0 || $stock_status === 'in-stock';
            });
        } elseif ($availability === 'out-of-stock') {
            $products = array_filter($products, function ($product) {
                $stock = (int) ($product['stock'] ?? 0);
                $stock_status = $product['stock_status'] ?? 'in-stock';
                return $stock <= 0 && $stock_status !== 'in-stock';
            });
        }

        // Pagination — every page link keeps  ?search=&category=&brand=&min_price=&max_price=&availability=&sort=&page=
        $perPage = 6;
        $currentPage = max(1, (int) $request->query('page', 1));
        $totalProducts = count($products);
                $pageItems = array_slice($products, ($currentPage - 1) * $perPage, $perPage, true);

        // Attach average star rating + approved-review count (from the existing
        // reviews feature) to each product on the current page so the catalogue
        // can render product ratings next to the product cards.
        foreach ($pageItems as $pSlug => $pItem) {
            $reviews = \App\Models\Review::approved()->where('product_slug', $pSlug);

            $pageItems[$pSlug]['avg_rating']   = (float) ($reviews->avg('rating') ?: 0);
            $pageItems[$pSlug]['review_count'] = (int) (\App\Models\Review::approved()->where('product_slug', $pSlug)->count());
        }

        $products = new LengthAwarePaginator($pageItems, $totalProducts, $perPage, $currentPage, [
            'path' => $request->url(),
        ]);
        $products->appends($request->query());

        // Wishlist state for the authenticated user (used by the heart toggles).
        $wishlistSlugs = auth()->check()
            ? WishlistItem::where('user_id', auth()->id())->pluck('product_slug')->all()
            : [];

        return view('products', compact('products', 'search', 'sort', 'category', 'categories', 'minPriceValid', 'maxPriceValid', 'wishlistSlugs'));
    })->name('products');

    Route::get('/products/create', function () {
        if (! in_array(auth()->user()->account_type, ['seller', 'admin'])) {
            return redirect()->route('products')->with('error', 'Only sellers or admins can add products.');
        }

        $categories = \App\Models\Category::all();

        return view('add-product', compact('categories'));
    })->name('products.create');

    Route::post('/products', function () use ($getCustomProducts, $saveCustomProducts, $seedProducts, $priceFloat) {
        if (! in_array(auth()->user()->account_type, ['seller', 'admin'])) {
            return redirect()->route('products')->with('error', 'Only sellers or admins can add products.');
        }
        $request = request();
        $data = $request->validate([
            'title'              => 'required|string|max:255',
            'sku'                => 'nullable|string|max:100',
            'subtitle'           => 'nullable|string|max:255',
            'description'        => 'required|string|max:5000',
            'price'              => 'required|numeric|min:0',
            'special_price'      => 'nullable|numeric|min:0',
            'quantity'           => 'required|integer|min:0',
            'stock_status'       => 'required|in:in-stock,out-of-stock,pre-order',
            'category'           => 'required|string|max:100',
            'subcategory'        => 'nullable|string|max:100',
            'brand'              => 'nullable|string|max:100',
            'tax'                => 'nullable|numeric|min:0|max:100',
            'status'             => 'nullable|in:0,1',
            'slug'               => 'nullable|string|max:255|regex:/^[a-z0-9\-]*$/',
            'tags'               => 'nullable|string|max:1000',
            'image'              => 'nullable|url|max:1000',
            'image_file'         => 'nullable|image|max:2048',
            'additional_images'  => 'nullable|string|max:5000',
            'image_files'        => 'nullable|array',
            'image_files.*'      => 'nullable|image|max:2048',
            'details'            => 'nullable|string|max:5000',
        ]);

        $cleanSlug = function ($value) {
            $slug = strtolower(trim($value));
            $slug = preg_replace('/[^a-z0-9\-]/', '-', $slug);
            $slug = preg_replace('/\-{2,}/', '-', $slug);
            return trim($slug, '-') ?: 'product';
        };

        // SEO Slug: use the manual slug when provided, otherwise derive from the title.
        $slug = $cleanSlug($data['slug'] ?? '');
        if (empty($data['slug'])) {
            $slug = $cleanSlug($data['title']);
        }
        $originalSlug = $slug;

        $customProducts = $getCustomProducts();
        $counter = 1;
        while (isset($customProducts[$slug]) || isset($seedProducts[$slug])) {
            $slug = $originalSlug . '-' . $counter++;
        }

        $details = array_values(array_filter(array_map('trim', explode("\n", $data['details'] ?? ''))));

        // Primary image: uploaded file > provided URL > default.
        $image = trim($data['image'] ?? '');
        if ($request->hasFile('image_file')) {
            $uploadDir = public_path('uploads/products');
            if (! is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            $file = $request->file('image_file');
            $filename = time() . '-' . mt_rand(1000, 9999) . '-' . Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) . '.' . $file->getClientOriginalExtension();
            $file->move($uploadDir, $filename);
            $image = '/uploads/products/' . $filename;
        }
        if ($image === '') {
            $image = 'https://images.unsplash.com/photo-1512436991641-6745cdb1723f?auto=format&fit=crop&w=800&q=80';
        }

        // Additional product images: URL list (one per line) + uploaded files.
        $extraImages = array_values(array_filter(array_map('trim', explode("\n", $data['additional_images'] ?? ''))));
        if ($request->hasFile('image_files')) {
            $uploadDir = public_path('uploads/products');
            if (! is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            foreach ($request->file('image_files') as $file) {
                $filename = time() . '-' . mt_rand(1000, 9999) . '-' . Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) . '.' . $file->getClientOriginalExtension();
                $file->move($uploadDir, $filename);
                $extraImages[] = '/uploads/products/' . $filename;
            }
        }
        $images = array_values(array_unique(array_merge([$image], $extraImages)));

        $tags = array_values(array_filter(array_map('trim', explode(',', $data['tags'] ?? ''))));

        // Product Options & Variants (OpenCart style). Parsed from the form and
        // stored on the product record; non-variant products simply get empty arrays.
        $options = [];
        $variants = [];
        if ($request->has('options') && is_array($request->input('options'))) {
            $options = ProductVariantService::normalizeOptions($request->input('options'));
            $variants = ProductVariantService::normalizeVariants(
                $options,
                $request->input('variants', []),
                $data['price'],
                $data['quantity']
            );
        }

        $customProducts[$slug] = [
            'title'          => $data['title'],
            'sku'            => ($data['sku'] ?? '') ?: strtoupper($slug),
            'subtitle'       => ($data['subtitle'] ?? '') ?: 'No subtitle provided.',
            'description'    => $data['description'],
            'image'          => $image,
            'images'         => $images,
            'details'        => $details ?: ['No additional details provided.'],
            'price'          => (float) $priceFloat($data['price']),
            'special_price'  => (($data['special_price'] ?? '') !== null && trim($data['special_price'] ?? '') !== '')
                                ? (float) $priceFloat($data['special_price']) : null,
            'quantity'       => (int) $data['quantity'],
            'stock_status'   => $data['stock_status'],
            'category'       => $data['category'],
            'subcategory'    => ($data['subcategory'] ?? '') ?: null,
            'brand'          => ($data['brand'] ?? '') ?: null,
            'tax'            => (($data['tax'] ?? '') !== null && trim($data['tax'] ?? '') !== '') ? (float) $data['tax'] : 0,
            'status'         => (int) ($data['status'] ?? 1),
            'slug'           => $slug,
            'tags'           => $tags,
            'options'        => $options,
            'variants'       => $variants,
        ];

        $saveCustomProducts($customProducts);

        return redirect()->route('products')->with('success', 'Product added successfully.');
    })->name('products.store');

    Route::get('/products/{product}', function ($product) use ($allProducts, $getCustomProducts, $priceOf, $priceFloat) {
        $products = $allProducts();
        $customProducts = $getCustomProducts();

        if (! isset($products[$product])) {
            abort(404);
        }

        // Buyers cannot view disabled products (OpenCart-style status toggle).
        if (auth()->user()->account_type === 'buyer' && isset($products[$product]['status']) && (int) $products[$product]['status'] === 0) {
            return redirect()->route('products')->with('error', 'This product is no longer available.');
        }

        // Track the view for the admin "Products Viewed" report.
        \App\Models\ProductView::recordView($product, $products[$product]['title'] ?? null);

        $categories = \App\Models\Category::all();

        return view('product-detail', [
            'product' => $products[$product],
            'slug' => $product,
            'customProducts' => $customProducts,
            'categories' => $categories,
        ]);
    })->name('product.show');

    Route::get('/products/{product}/edit', function ($product) use ($allProducts, $getCustomProducts) {
        $products = $allProducts();

        if (! in_array(auth()->user()->account_type, ['seller', 'admin'])) {
            return redirect()->route('products')->with('error', 'Only sellers or admins can edit products.');
        }

        if (! isset($products[$product])) {
            abort(404);
        }

        $customProducts = $getCustomProducts();
        $categories = \App\Models\Category::all();

        return view('edit-product', [
            'product' => $products[$product],
            'slug' => $product,
            'customProducts' => $customProducts,
            'categories' => $categories,
        ]);
    })->name('products.edit');

    Route::post('/products/{product}/update', function ($product) use ($allProducts, $getCustomProducts, $saveCustomProducts, $priceFloat) {
        if (! in_array(auth()->user()->account_type, ['seller', 'admin'])) {
            return redirect()->route('products')->with('error', 'Only sellers or admins can update products.');
        }

        $allProds = $allProducts();
        if (! isset($allProds[$product])) {
            abort(404);
        }

        $request = request();
        $data = $request->validate([
            'title'              => 'required|string|max:255',
            'sku'                => 'nullable|string|max:100',
            'subtitle'           => 'nullable|string|max:255',
            'description'        => 'required|string|max:5000',
            'price'              => 'required|numeric|min:0',
            'special_price'      => 'nullable|numeric|min:0',
            'quantity'           => 'required|integer|min:0',
            'stock_status'       => 'required|in:in-stock,out-of-stock,pre-order',
            'category'           => 'required|string|max:100',
            'subcategory'        => 'nullable|string|max:100',
            'brand'              => 'nullable|string|max:100',
            'tax'                => 'nullable|numeric|min:0|max:100',
            'status'             => 'nullable|in:0,1',
            'slug'               => 'nullable|string|max:255|regex:/^[a-z0-9\-]*$/',
            'tags'               => 'nullable|string|max:1000',
            'image'              => 'nullable|url|max:1000',
            'image_file'         => 'nullable|image|max:2048',
            'additional_images'  => 'nullable|string|max:5000',
            'image_files'        => 'nullable|array',
            'image_files.*'      => 'nullable|image|max:2048',
            'details'            => 'nullable|string|max:5000',
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

        // Additional images: new URLs + uploaded files + existing gallery (excluding old main).
        $extraImages = array_values(array_filter(array_map('trim', explode("\n", $data['additional_images'] ?? ''))));
        if ($request->hasFile('image_files')) {
            $uploadDir = public_path('uploads/products');
            if (! is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            foreach ($request->file('image_files') as $file) {
                $filename = time() . '-' . mt_rand(1000, 9999) . '-' . Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) . '.' . $file->getClientOriginalExtension();
                $file->move($uploadDir, $filename);
                $extraImages[] = '/uploads/products/' . $filename;
            }
        }
        $existingGallery = array_values(array_filter($allProds[$product]['images'] ?? []));
        $images = array_values(array_unique(array_merge([$image], $extraImages, $existingGallery)));

        $tags = array_values(array_filter(array_map('trim', explode(',', $data['tags'] ?? ''))));

        // SEO slug: manual override, otherwise keep existing, otherwise derive from the URL slug.
        $cleanSlug = function ($value) {
            $slug = strtolower(trim($value));
            $slug = preg_replace('/[^a-z0-9\-]/', '-', $slug);
            $slug = preg_replace('/\-{2,}/', '-', $slug);
            return trim($slug, '-') ?: 'product';
        };
        $slug = $cleanSlug($data['slug'] ?? '');
        if (($data['slug'] ?? null) === null || trim($data['slug'] ?? '') === '') {
            $slug = $allProds[$product]['slug'] ?? $cleanSlug($product);
        }

        // Product Options & Variants (OpenCart style). Existing non-variant
        // products continue to work unchanged when no options are submitted.
        $options = [];
        $variants = [];
        if ($request->has('options') && is_array($request->input('options'))) {
            $options = ProductVariantService::normalizeOptions($request->input('options'));
            $variants = ProductVariantService::normalizeVariants(
                $options,
                $request->input('variants', []),
                $data['price'],
                $data['quantity']
            );
        }

        $customProducts[$product] = [
            'title'          => $data['title'],
            'sku'            => ($data['sku'] ?? '') ?: ($allProds[$product]['sku'] ?? strtoupper($product)),
            'subtitle'       => ($data['subtitle'] ?? '') ?: 'No subtitle provided.',
            'description'    => $data['description'],
            'image'          => $image,
            'images'         => $images,
            'details'        => $details ?: ['No additional details provided.'],
            'price'          => (float) $priceFloat($data['price']),
            'special_price'  => (($data['special_price'] ?? null) !== null && ($data['special_price'] ?? '') !== '')
                                ? (float) $priceFloat($data['special_price']) : null,
            'quantity'       => (int) $data['quantity'],
            'stock_status'   => $data['stock_status'],
            'category'       => $data['category'],
            'subcategory'    => ($data['subcategory'] ?? '') ?: null,
            'brand'          => ($data['brand'] ?? '') ?: null,
            'tax'            => (($data['tax'] ?? null) !== null && ($data['tax'] ?? '') !== '') ? (float) $data['tax'] : 0,
            'status'         => (int) ($data['status'] ?? 1),
            'slug'           => $slug,
            'tags'           => $tags,
            'options'        => $options,
            'variants'       => $variants,
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
        foreach (array_keys($cart) as $key) {
            if ($key === $product || ProductVariantService::isVariantLine($key, $product)) {
                unset($cart[$key]);
            }
        }
        session(['cart' => $cart]);

        return redirect()->route('products')->with('success', 'Product removed successfully.');
    })->name('products.destroy');

    Route::post('/products/{slug}/reviews', [ReviewsController::class, 'store'])
        ->middleware('auth')
        ->name('products.reviews.store');
        // Admin Review Management
Route::middleware('auth')->group(function () {

    Route::get('/admin/reviews', [ReviewsController::class, 'index'])
        ->name('admin.reviews.index');

    Route::get('/admin/reviews/{review}/edit', [ReviewsController::class, 'edit'])
        ->name('admin.reviews.edit');

    Route::put('/admin/reviews/{review}', [ReviewsController::class, 'update'])
        ->name('admin.reviews.update');

    Route::delete('/admin/reviews/{review}', [ReviewsController::class, 'destroy'])
        ->name('admin.reviews.destroy');

    Route::post('/admin/reviews/{review}/approve', [ReviewsController::class, 'approve'])
        ->name('admin.reviews.approve');

    Route::post('/admin/reviews/{review}/reject', [ReviewsController::class, 'reject'])
        ->name('admin.reviews.reject');

    // Admin Order Management
    Route::get('/admin/orders', [AdminOrdersController::class, 'index'])
        ->name('admin.orders.index');

    Route::post('/admin/orders/{order}/status', [AdminOrdersController::class, 'updateStatus'])
        ->name('admin.orders.status');

    // Admin Coupon Management
    Route::get('/admin/coupons', [CouponsController::class, 'index'])
        ->name('admin.coupons.index');
    Route::post('/admin/coupons', [CouponsController::class, 'store'])
        ->name('admin.coupons.store');
    Route::get('/admin/coupons/{coupon}/edit', [CouponsController::class, 'edit'])
        ->name('admin.coupons.edit');
    Route::put('/admin/coupons/{coupon}', [CouponsController::class, 'update'])
        ->name('admin.coupons.update');
    Route::delete('/admin/coupons/{coupon}', [CouponsController::class, 'destroy'])
        ->name('admin.coupons.destroy');

    // Admin Order Invoice (Sales > Customers > Order history)
    Route::get('/admin/orders/{order}/invoice', [AdminOrdersController::class, 'invoice'])
        ->middleware('admin')
        ->name('admin.orders.invoice');

    // Admin Customer Management (Sales > Customers)
    Route::middleware('admin')->prefix('admin/customers')->group(function () {
        Route::get('/', [AdminCustomersController::class, 'index'])
            ->name('admin.customers.index');

        Route::get('/{customer}', [AdminCustomersController::class, 'show'])
            ->name('admin.customers.show');

        Route::get('/{customer}/edit', [AdminCustomersController::class, 'edit'])
            ->name('admin.customers.edit');

        Route::put('/{customer}', [AdminCustomersController::class, 'update'])
            ->name('admin.customers.update');

        Route::post('/{customer}/status', [AdminCustomersController::class, 'updateStatus'])
            ->name('admin.customers.status');
    });

    /*
    |--------------------------------------------------------------------------
    | OpenCart-style Admin Panel
    |--------------------------------------------------------------------------
    | Dashboard + Catalog / Sales / Marketing / Reports / System modules.
    | Every route sits behind the "admin" middleware (admins & managers);
    | destructive actions additionally require granular group permissions.
    */
    Route::middleware('admin')->group(function () {
        // Dashboard
        Route::get('/admin/dashboard', [AdminDashboardController::class, 'index'])
            ->name('admin.dashboard');

        // Helper: read-only resource routes + permission-guarded mutations.
        $permResource = function (
            string $uri,
            string $controller,
            string $namePrefix,
            string $module,
            string $param
        ) {
            Route::resource($uri, $controller)
                ->names($namePrefix)
                ->only(['index', 'create', 'edit']);

            Route::post($uri, [$controller, 'store'])
                ->middleware("perm:{$module},create")
                ->name($namePrefix . '.store');

            Route::put($uri . '/{' . $param . '}', [$controller, 'update'])
                ->middleware("perm:{$module},edit")
                ->name($namePrefix . '.update');

            Route::delete($uri . '/{' . $param . '}', [$controller, 'destroy'])
                ->middleware("perm:{$module},delete")
                ->name($namePrefix . '.destroy');
        };

        // Catalog
        $permResource('admin/manufacturers', AdminManufacturersController::class, 'admin.manufacturers', 'catalog', 'manufacturer');
        $permResource('admin/options', AdminOptionsController::class, 'admin.options', 'catalog', 'option');
        $permResource('admin/categories', AdminCategoriesController::class, 'admin.categories', 'catalog', 'category');

        // Sales > Returns
        Route::get('/admin/returns', [AdminReturnsController::class, 'index'])->name('admin.returns.index');
        Route::get('/admin/returns/create', [AdminReturnsController::class, 'create'])->name('admin.returns.create');
        Route::post('/admin/returns', [AdminReturnsController::class, 'store'])->middleware('perm:sales,create')->name('admin.returns.store');
        Route::get('/admin/returns/{return}', [AdminReturnsController::class, 'show'])->name('admin.returns.show');
        Route::post('/admin/returns/{return}/status', [AdminReturnsController::class, 'updateStatus'])->middleware('perm:sales,edit')->name('admin.returns.status');
        Route::delete('/admin/returns/{return}', [AdminReturnsController::class, 'destroy'])->middleware('perm:sales,delete')->name('admin.returns.destroy');

        // Marketing > Promotions
        $permResource('admin/promotions', AdminPromotionsController::class, 'admin.promotions', 'marketing', 'promotion');

        // Marketing > Newsletter
        Route::get('/admin/newsletter', [AdminNewsletterController::class, 'index'])->name('admin.newsletter.index');
        Route::post('/admin/newsletter', [AdminNewsletterController::class, 'store'])->middleware('perm:marketing,create')->name('admin.newsletter.store');
        Route::get('/admin/newsletter/compose', [AdminNewsletterController::class, 'compose'])->name('admin.newsletter.compose');
        Route::post('/admin/newsletter/send', [AdminNewsletterController::class, 'send'])->middleware('perm:marketing,create')->name('admin.newsletter.send');
        Route::post('/admin/newsletter/{subscriber}/toggle', [AdminNewsletterController::class, 'toggle'])->middleware('perm:marketing,edit')->name('admin.newsletter.toggle');
        Route::delete('/admin/newsletter/{subscriber}', [AdminNewsletterController::class, 'destroy'])->middleware('perm:marketing,delete')->name('admin.newsletter.destroy');

        // Reports (+ CSV exports)
        Route::get('/admin/reports/sales', [AdminReportsController::class, 'sales'])->name('admin.reports.sales');
        Route::get('/admin/reports/sales/export', [AdminReportsController::class, 'exportSales'])->name('admin.reports.sales.export');
        Route::get('/admin/reports/viewed', [AdminReportsController::class, 'viewed'])->name('admin.reports.viewed');
        Route::get('/admin/reports/viewed/export', [AdminReportsController::class, 'exportViewed'])->name('admin.reports.viewed.export');
        Route::get('/admin/reports/purchased', [AdminReportsController::class, 'purchased'])->name('admin.reports.purchased');
        Route::get('/admin/reports/purchased/export', [AdminReportsController::class, 'exportPurchased'])->name('admin.reports.purchased.export');
        Route::get('/admin/reports/customers', [AdminReportsController::class, 'customers'])->name('admin.reports.customers');
        Route::get('/admin/reports/customers/export', [AdminReportsController::class, 'exportCustomers'])->name('admin.reports.customers.export');

        // System > Users (staff accounts)
        Route::get('/admin/system/users', [AdminSystemUsersController::class, 'index'])->name('admin.system.users.index');
        Route::get('/admin/system/users/create', [AdminSystemUsersController::class, 'create'])->middleware('perm:system,create')->name('admin.system.users.create');
        Route::post('/admin/system/users', [AdminSystemUsersController::class, 'store'])->middleware('perm:system,create')->name('admin.system.users.store');
        Route::get('/admin/system/users/{user}/edit', [AdminSystemUsersController::class, 'edit'])->middleware('perm:system,edit')->name('admin.system.users.edit');
        Route::put('/admin/system/users/{user}', [AdminSystemUsersController::class, 'update'])->middleware('perm:system,edit')->name('admin.system.users.update');
        Route::delete('/admin/system/users/{user}', [AdminSystemUsersController::class, 'destroy'])->middleware('perm:system,delete')->name('admin.system.users.destroy');

        // System > User Groups
        Route::get('/admin/system/groups', [AdminUserGroupsController::class, 'index'])->name('admin.system.groups.index');
        Route::get('/admin/system/groups/create', [AdminUserGroupsController::class, 'create'])->middleware('perm:system,create')->name('admin.system.groups.create');
        Route::post('/admin/system/groups', [AdminUserGroupsController::class, 'store'])->middleware('perm:system,create')->name('admin.system.groups.store');
        Route::get('/admin/system/groups/{group}/edit', [AdminUserGroupsController::class, 'edit'])->middleware('perm:system,edit')->name('admin.system.groups.edit');
        Route::put('/admin/system/groups/{group}', [AdminUserGroupsController::class, 'update'])->middleware('perm:system,edit')->name('admin.system.groups.update');
        Route::delete('/admin/system/groups/{group}', [AdminUserGroupsController::class, 'destroy'])->middleware('perm:system,delete')->name('admin.system.groups.destroy');

        // System > Settings
        Route::get('/admin/settings', [AdminSettingsController::class, 'index'])->name('admin.settings.index');
        Route::put('/admin/settings', [AdminSettingsController::class, 'update'])->middleware('perm:system,edit')->name('admin.settings.update');

        // System > Backup
        Route::get('/admin/backups', [AdminBackupController::class, 'index'])->name('admin.backup.index');
        Route::post('/admin/backups', [AdminBackupController::class, 'create'])->middleware('perm:system,create')->name('admin.backup.create');
        Route::get('/admin/backups/{filename}/download', [AdminBackupController::class, 'download'])->name('admin.backup.download');
        Route::post('/admin/backups/{filename}/restore', [AdminBackupController::class, 'restore'])->middleware('perm:system,delete')->name('admin.backup.restore');
        Route::delete('/admin/backups/{filename}', [AdminBackupController::class, 'destroy'])->middleware('perm:system,delete')->name('admin.backup.destroy');
    });
});
    // Wishlist Routes (DB-backed, persistent per user)
    Route::get('/wishlist', [WishlistController::class, 'index'])->name('wishlist.index');
    Route::post('/wishlist/toggle/{product}', [WishlistController::class, 'toggle'])->name('wishlist.toggle');
    Route::post('/wishlist/remove/{product}', [WishlistController::class, 'remove'])->name('wishlist.remove');
    Route::post('/wishlist/to-cart/{product}', [WishlistController::class, 'toCart'])->name('wishlist.to-cart');

    // Order History & Tracking
    Route::get('/orders', [OrdersController::class, 'index'])->name('orders.index');
    Route::get('/orders/{order}', [OrdersController::class, 'show'])->name('orders.show');

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
