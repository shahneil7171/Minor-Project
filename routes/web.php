<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\AddressController;
use App\Http\Controllers\ReviewsController;
use App\Http\Controllers\WishlistController;
use App\Http\Controllers\OrdersController;
use App\Http\Controllers\AdminOrdersController;
use App\Http\Controllers\CouponsController;
use App\Models\WishlistItem;
use App\Models\Order;
use App\Models\Coupon;
use App\Models\OrderItem;
use App\Services\ProductVariantService;
use Illuminate\Support\Facades\Auth;
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

    return view('home', compact('featured', 'bestSellers', 'specialOffers', 'cartCount', 'wishlistCount', 'wishlistSlugs'));
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

    Route::post('/cart/add/{product}', function ($product) use ($allProducts, $priceOf) {
        if (auth()->user()->account_type === 'seller') {
            return redirect()->route('products')->with('error', 'Sellers cannot add items to cart.');
        }
        $products = $allProducts();

        if (!isset($products[$product])) {
            abort(404);
        }

        $prod = $products[$product];

        $data = request()->validate([
            'quantity'   => 'nullable|integer|min:1',
            'variant_id' => 'nullable|string|max:100',
        ]);
        $quantity  = (int) ($data['quantity'] ?? 1);
        $variant   = null;
        $hasOptions = ! empty($prod['options'] ?? []);

        $variantId = $data['variant_id'] ?? null;
        if ($hasOptions) {
            $variant = ProductVariantService::findVariant($prod, $variantId);
            if (! $variant) {
                return redirect()->route('product.show', ['product' => $product])
                    ->with('error', 'Please select all the required options before adding this product to your cart.');
            }
        }

        $price = $variant ? (float) $variant['price'] : $priceOf($prod);
        $stock = $variant ? (int) $variant['stock'] : (int) ($prod['quantity'] ?? 0);

        $cartKey = ProductVariantService::cartKey($product, $variant ? $variant['id'] : null);

        $cart = session()->get('cart', []);
        $currentQty = isset($cart[$cartKey]) ? $cart[$cartKey]['quantity'] : 0;

        if ($stock > 0 && $currentQty + $quantity > $stock) {
            return redirect()->route('cart.index')
                ->with('error', 'Sorry, only ' . $stock . ' unit(s) of this item are available.');
        }

        if (isset($cart[$cartKey])) {
            $cart[$cartKey]['quantity'] += $quantity;
        } else {
            $cart[$cartKey] = [
                'product'         => $product,
                'title'           => $prod['title'],
                'price'           => $price,
                'quantity'        => $quantity,
                'selected_options'=> $variant ? $variant['values'] : [],
                'options_text'    => $variant ? ProductVariantService::describeVariant($variant) : '',
                'sku'             => $variant ? ($variant['sku'] ?? null) : ($prod['sku'] ?? null),
                'variant_id'      => $variant ? $variant['id'] : null,
            ];
        }

        session(['cart' => $cart]);

        return redirect()->route('cart.index')
            ->with('success', 'Product added to cart!');
    })->name('cart.add');

    Route::post('/cart/buy-now/{product}', function ($product) use ($allProducts, $priceOf) {
        if (auth()->user()->account_type === 'seller') {
            return redirect()->route('products')->with('error', 'Sellers cannot purchase items.');
        }
        $products = $allProducts();
        if (! isset($products[$product])) {
            abort(404);
        }

        $prod = $products[$product];

        $data = request()->validate([
            'quantity'   => 'required|integer|min:1',
            'variant_id' => 'nullable|string|max:100',
        ]);

        $quantity   = (int) $data['quantity'];
        $variant    = null;
        $hasOptions = ! empty($prod['options'] ?? []);
        $variantId  = $data['variant_id'] ?? null;

        if ($hasOptions) {
            $variant = ProductVariantService::findVariant($prod, $variantId);
            if (! $variant) {
                return redirect()->route('product.show', ['product' => $product])
                    ->with('error', 'Please select all the required options before buying this product.');
            }
        }

        $price   = $variant ? (float) $variant['price'] : $priceOf($prod);
        $cartKey = ProductVariantService::cartKey($product, $variant ? $variant['id'] : null);

        $cart = session()->get('cart', []);
        $cart[$cartKey] = [
            'product'         => $product,
            'title'           => $prod['title'],
            'price'           => $price,
            'quantity'        => $quantity,
            'selected_options'=> $variant ? $variant['values'] : [],
            'options_text'    => $variant ? ProductVariantService::describeVariant($variant) : '',
            'sku'             => $variant ? ($variant['sku'] ?? null) : ($prod['sku'] ?? null),
            'variant_id'      => $variant ? $variant['id'] : null,
        ];
        session(['cart' => $cart, 'buy_now_item' => ['product' => $cartKey, 'quantity' => $quantity]]);

        return redirect()->route('checkout.review');
    })->name('cart.buy-now');
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
});
    Route::get('/checkout/review', function () {
        $cart = session()->get('cart', []);
        $buyNow = session()->get('buy_now_item');

        if ($buyNow) {
            if (! isset($cart[$buyNow['product']])) {
                return redirect()->route('cart.index');
            }

            // Preserve the full cart line (title, price, selected options, sku)
            // so the chosen variant stays visible through review and checkout.
            $item = $cart[$buyNow['product']];
            $item['quantity'] = $buyNow['quantity'];
            $cart = [$buyNow['product'] => $item];
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

    Route::post('/cart/increase/{product}', function ($product) use ($allProducts) {
        $cart = session()->get('cart', []);

        if (isset($cart[$product])) {
            $item = $cart[$product];
            $products = $allProducts();
            $cap = null;

            // Enforce variant stock limits when this line refers to a variant.
            if (! empty($item['variant_id'])) {
                $baseSlug = explode('::', $item['product'] ?? $product)[0];
                if (isset($products[$baseSlug])) {
                    $variant = ProductVariantService::findVariant($products[$baseSlug], $item['variant_id']);
                    if ($variant) {
                        $cap = (int) $variant['stock'];
                    }
                }
            }

            if ($cap === null || $item['quantity'] < $cap) {
                $cart[$product]['quantity'] += 1;
                session(['cart' => $cart]);
            } elseif ($cap > 0) {
                return redirect()->route('cart.index')
                    ->with('error', 'Sorry, only ' . $cap . ' unit(s) of this item are available.');
            }
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
            'coupon_code' => 'nullable|string|max:50',
            'payment_method' => 'nullable|string|in:cod,card,upi,paypal',
        ]);

        $cart = session()->get('cart', []);
        $buyNow = session()->get('buy_now_item');
        $order = $cart;

        if ($buyNow) {
            $product = $buyNow['product'];

            if (! isset($cart[$product])) {
                return redirect()->route('cart.index');
            }

            $line = $cart[$product];
            $line['quantity'] = $buyNow['quantity'];

            $order = [
                $product => $line,
            ];

            unset($cart[$product]);
            session(['cart' => $cart]);
            session()->forget('buy_now_item');
        } else {
            session()->forget('cart');
        }

        // -------------------------------------------------------------
        //  Persist the order so the buyer can view history and tracking.
        //  Products are JSON-backed, so each line stores a full snapshot.
        // -------------------------------------------------------------
        $catalog = app(\App\Services\ProductCatalogService::class)->all();

        do {
            $orderNumber = 'KDP-' . date('ymd') . '-' . strtoupper(Str::random(6));
        } while (Order::where('order_number', $orderNumber)->exists());

        $paymentMethods = [
            'cod' => 'Cash on Delivery',
            'card' => 'Card Payment',
            'upi' => 'UPI',
            'paypal' => 'PayPal',
        ];
        $paymentMethod = $data['payment_method'] ?? 'cod';

        $orderRecord = Order::create([
            'user_id'         => auth()->id(),
            'order_number'    => $orderNumber,
            'status'          => 'pending',
            'subtotal'        => 0,
            'tax'             => 0,
            'shipping_cost'   => 0,
            'total'           => 0,
            'payment_method'  => $paymentMethods[$paymentMethod] ?? 'Cash on Delivery',
            'shipping_name'   => $data['name'],
            'shipping_phone'  => $data['phone'],
            'shipping_address'=> $data['address'],
            'shipping_city'   => $data['city'],
            'shipping_state'  => $data['state'],
            'shipping_pincode'=> $data['pincode'],
            'notes'           => null,
        ]);

        $orderSubtotal = 0;

        foreach ($order as $key => $item) {
            // Cart keys can be "slug" or "slug::variant-id"; strip the variant part.
            $baseSlug = explode('::', (string) $key)[0];
            $product  = $catalog[$baseSlug] ?? null;

            $price     = (float) filter_var((string) ($item['price'] ?? 0), FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
            $quantity  = (int) ($item['quantity'] ?? 1);
            $lineTotal = round($price * $quantity, 2);

            OrderItem::create([
                'order_id'      => $orderRecord->id,
                'product_slug'  => $baseSlug,
                'product_title' => $item['title'] ?? ($product['title'] ?? 'Product'),
                'product_image' => $product['image'] ?? null,
                'sku'           => $item['sku'] ?? null,
                'price'         => $price,
                'quantity'      => $quantity,
                'subtotal'      => $lineTotal,
                'options_text'  => $item['options_text'] ?? null,
            ]);

            $orderSubtotal += $lineTotal;
        }

        // -------------------------------------------------------------
        //  Apply an optional coupon code discount to the order.
        // -------------------------------------------------------------
        $discountAmount = 0.0;
        $couponCode = null;

        if (! empty($data['coupon_code'])) {
            $coupon = \App\Models\Coupon::active()
                ->where('code', strtoupper(trim($data['coupon_code'])))
                ->first();

            if ($coupon && $coupon->isValidFor($orderSubtotal)) {
                $discountAmount = $coupon->discountFor($orderSubtotal);
                $couponCode = $coupon->code;

                $coupon->increment('used_count');
            }
        }

        $finalTotal = max(0.0, round($orderSubtotal - $discountAmount, 2));

        $orderRecord->update([
            'subtotal'       => $orderSubtotal,
            'discount_amount' => $discountAmount,
            'coupon_code'    => $couponCode,
            'total'          => $finalTotal,
        ]);

        session(['order' => $order, 'checkout' => $data, 'order_id' => $orderRecord->id, 'order_total' => $finalTotal, 'order_discount' => $discountAmount, 'order_coupon' => $couponCode, 'order_payment' => $paymentMethods[$paymentMethod] ?? 'Cash on Delivery']);

        return redirect()->route('checkout.complete');
    })->name('checkout.submit');

    Route::get('/checkout/complete', function () {
        $cart = session()->get('order', []);
        $checkout = session()->get('checkout', []);

        if (empty($cart) || empty($checkout)) {
            return redirect()->route('cart.index');
        }

        return view('checkout-complete', [
            'cart' => $cart,
            'checkout' => $checkout,
            'orderId' => session('order_id'),
        ]);
    })->name('checkout.complete');

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
