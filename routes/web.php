<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\AddressController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\ReviewsController;
use App\Http\Controllers\WishlistController;
use App\Http\Controllers\OrdersController;
use App\Http\Controllers\ReturnsController;
use App\Http\Controllers\SellerReturnsController;
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
use App\Http\Controllers\AdminDeliveriesController;
use App\Http\Controllers\AdminDeliveryPartnersController;
use App\Http\Controllers\DeliveryController;
use App\Http\Controllers\SellerOrdersController;
use App\Http\Controllers\SellerProductController;
use App\Http\Controllers\SellerInventoryController;
use App\Http\Controllers\AdminInventoryController;
use App\Http\Controllers\SellerPaymentSettingsController;
use App\Http\Controllers\AdminSellerPaymentsController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\NotificationsController;
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
| Product category validation (shared by product create + update)
|--------------------------------------------------------------------------
| Server-side enforcement of the category rules (never rely on the form's
| JavaScript alone):
|
|  - `category` must resolve to an EXISTING main (top-level) category;
|  - `subcategory` (optional) must resolve to a category that actually
|    belongs to the selected main category — invalid combinations such as
|    Electronics + Men's Clothing are rejected;
|  - disabled categories cannot be selected for NEW products, while an
|    update may keep the product's current (now disabled) category.
|
| Identifiers accept ids, slugs or exact names (the forms submit ids), and
| an unresolvable subcategory string falls back to the pre-existing
| legacy "free-text label" behaviour so older records keep working.
|
| Returns the final selected category (the subcategory when one is chosen,
| otherwise the main category) plus the stored subcategory label.
*/
$resolveProductCategory = function (array $data, ?int $unchangedCategoryId = null): array {
    $catalog = app(\App\Services\ProductCatalogService::class);
    $reject = fn (string $field, string $message) => \Illuminate\Validation\ValidationException::withMessages([$field => $message]);

    $parentCategory = $catalog->resolveCategory($data['category'] ?? null);

    if (! $parentCategory) {
        throw $reject('category', 'The selected category does not exist.');
    }

    if ($parentCategory->parent_id !== null) {
        throw $reject('category', 'Please select a main category. Subcategories are chosen in the subcategory field.');
    }

    $subcategoryInput = trim((string) ($data['subcategory'] ?? ''));
    $subcategory = null;
    $subcategoryLabel = null;

    if ($subcategoryInput !== '') {
        $subcategory = $catalog->resolveCategory($subcategoryInput);

        if ($subcategory) {
            if ((int) $subcategory->parent_id !== (int) $parentCategory->id) {
                throw $reject('subcategory', 'The selected subcategory does not belong to the selected category.');
            }

            $subcategoryLabel = $subcategory->name;
        } elseif (ctype_digit($subcategoryInput)) {
            // A numeric id that matches no category row is always invalid.
            throw $reject('subcategory', 'The selected subcategory does not exist.');
        } else {
            // Legacy free-text subcategory label (records that predate the
            // subcategory dropdown): kept verbatim, exactly as before.
            $subcategoryLabel = $subcategoryInput;
        }
    }

    $selectedCategory = $subcategory ?? $parentCategory;
    $isUnchanged = $unchangedCategoryId !== null
        && (int) $unchangedCategoryId === (int) $selectedCategory->id;

    if (! $isUnchanged) {
        if (! $parentCategory->is_active) {
            throw $reject('category', 'The selected category is disabled. Choose an active category.');
        }

        if ($subcategory && ! $subcategory->is_active) {
            throw $reject('subcategory', 'The selected subcategory is disabled. Choose an active subcategory.');
        }
    }

    return [
        'selected'         => $selectedCategory,
        'subcategoryLabel' => $subcategoryLabel,
    ];
};

/*
|--------------------------------------------------------------------------
| Store Home
|--------------------------------------------------------------------------
| A public storefront every visitor can browse. Logged-in shoppers get
| working cart / wishlist actions, guests are guided to sign in.
*/
Route::get('/', function () use ($allProducts) {
    $catalog = app(\App\Services\ProductCatalogService::class);

    // Only show products whose "status" flag is enabled (OpenCart-style status).
    // Slug keys are preserved so every product card links to its canonical
    // /products/{slug} URL.
    $enabled = array_filter($allProducts(), function ($p) {
        return ! isset($p['status']) || (int) $p['status'] === 1;
    });

    // Preserve the associative slug keys (preserve_keys = true) so the views
    // always receive real slugs, never numeric indexes.
    $featured = array_slice($enabled, 0, 4, true);
    $bestSellers = array_slice($enabled, 4, 4, true);
    $specialOffers = array_slice($enabled, 0, 4, true);

    $cartCount = app(\App\Services\CartService::class)->count();

    $wishlistSlugs = auth()->check()
        ? WishlistItem::where('user_id', auth()->id())->pluck('product_slug')->all()
        : [];
    $wishlistCount = count($wishlistSlugs);

    // Active promotional banners from the admin Marketing module.
    $promotions = \App\Models\Promotion::active()->get();

    // ---------------------------------------------------------------------
    // Category organisation is driven entirely by the database `categories`
    // table: every tile/section below is a real Category record, and its
    // products come from ProductCatalogService::productsForCategory() which
    // matches the stored category relationship (never the product name).
    // Creating a new category in the admin panel automatically works here.
    // ---------------------------------------------------------------------
    $homeCategories = collect();
    $categorySections = [];
    $categoryCounts = [];

    // Single pass over the enabled catalog building a count per category id.
    // Feeds the per-subcategory counters in the Categories mega menu without
    // introducing one query per category (no N+1).
    $productsPerCategory = [];

    foreach ($enabled as $catalogProduct) {
        if (! isset($catalogProduct['category_id']) || $catalogProduct['category_id'] === null) {
            continue;
        }

        $catalogCategoryId = (int) $catalogProduct['category_id'];
        $productsPerCategory[$catalogCategoryId] = ($productsPerCategory[$catalogCategoryId] ?? 0) + 1;
    }

    try {
        $homeCategories = \App\Models\Category::query()
            ->active()
            ->parent()
            ->ordered()
            ->with(['children' => function ($query) {
                $query->active()->ordered();
            }])
            ->get();
    } catch (\Throwable $e) {
        // Categories table may not exist yet on a fresh install.
        $homeCategories = collect();
    }

    foreach ($homeCategories as $category) {
        // Database-driven product count per top-level category (includes
        // its sub-categories' products). Computed in memory from the
        // catalog already loaded above — no per-category queries, matching
        // ProductCatalogService::productsForCategory() exactly.
        $categoryIds = [$category->id];
        foreach ($category->children as $child) {
            $categoryIds[] = $child->id;
        }

        $categoryProducts = array_filter($enabled, function ($p) use ($categoryIds) {
            return isset($p['category_id'])
                && $p['category_id'] !== null
                && in_array((int) $p['category_id'], $categoryIds, true);
        });

        $categoryCounts[$category->id] = count($categoryProducts);

        // Per-subcategory counts for the mega menu come from the same in-memory
        // map, so the menu never issues a query of its own.
        foreach ($category->children as $child) {
            $categoryCounts[$child->id] = $productsPerCategory[$child->id] ?? 0;
        }

        if (empty($categoryProducts)) {
            continue;
        }

        $categorySections[] = [
            'category' => $category,
            'items'    => array_slice($categoryProducts, 0, 4, true),
            'total'    => count($categoryProducts),
        ];
    }

    return view('home', compact(
        'featured', 'bestSellers', 'specialOffers', 'cartCount',
        'wishlistCount', 'wishlistSlugs', 'promotions',
        'homeCategories', 'categorySections', 'categoryCounts'
    ));
})->name('home');

/*
|--------------------------------------------------------------------------
| Category Listing Page
|--------------------------------------------------------------------------
| /categories/{slug} — a real database-driven category page. Products are
| selected through products.category_id (never text search). Parent
| categories include their sub-categories' products, matching the home
| page category sections.
*/
Route::get('/categories/{slug}', function (string $slug) {
    $catalogService = app(\App\Services\ProductCatalogService::class);

    $category = \App\Models\Category::query()
        ->where('slug', $slug)
        ->active()
        ->with(['children' => fn ($q) => $q->active()->ordered()])
        ->first();

    if (! $category) {
        abort(404);
    }

    // category_id is the single source of truth for this listing.
    $products = $catalogService->productsForCategory($category);

    return view('categories.show', [
        'category'      => $category,
        'products'      => $products,
        'productCount'  => count($products),
    ]);
})->name('categories.show');

/*
|--------------------------------------------------------------------------
| Storefront Info Pages
|--------------------------------------------------------------------------
| Deals / About Us / Contact — public pages reachable from the main
| navigation by guests and customers alike.
*/
Route::get('/deals', function () use ($allProducts) {
    $catalogService = app(\App\Services\ProductCatalogService::class);

    // Real catalog data: products with a special price that is lower than
    // the base price, biggest savings first. Never fake/static content.
    $deals = [];
    foreach ($allProducts() as $slug => $product) {
        if (! $catalogService->isProductEnabled($product)) {
            continue;
        }

        // Raw base price — NOT the effective/sale price.
        $base = (float) filter_var((string) ($product['price'] ?? 0), FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
        $specialRaw = $product['special_price'] ?? null;
        $special = ($specialRaw !== null && $specialRaw !== '') ? (float) filter_var((string) $specialRaw, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION) : 0;

        if ($base <= 0 || $special <= 0 || $special >= $base) {
            continue;
        }

        $product['base_price'] = round($base, 2);
        $product['deal_price'] = $special;
        $product['discount_percent'] = $base > 0 ? (int) round((($base - $special) / $base) * 100) : 0;
        $deals[$slug] = $product;
    }

    uasort($deals, fn ($a, $b) => $b['discount_percent'] <=> $a['discount_percent']);

    return view('deals', [
        'deals' => array_slice($deals, 0, 12, true),
    ]);
})->name('deals');

Route::get('/about', function () {
    return view('about');
})->name('about');

Route::get('/contact', function () {
    return view('contact');
})->name('contact');

Route::post('/contact', function (\Illuminate\Http\Request $request) {
    $data = $request->validate([
        'name'    => ['required', 'string', 'max:255'],
        'email'   => ['required', 'email', 'max:255'],
        'subject' => ['nullable', 'string', 'max:255'],
        'message' => ['required', 'string', 'min:10', 'max:2000'],
    ]);

    // Record the enquiry so it is not silently discarded. Delivery to an
    // inbox can be added later without changing the form or validation.
    \Illuminate\Support\Facades\Log::info('Contact form submission', $data);

    return redirect()
        ->route('contact')
        ->with('success', 'Thanks ' . $data['name'] . ', your message has been received. Our team will get back to you at ' . $data['email'] . '.');
})->name('contact.submit');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.post')->middleware('throttle:10,1');
    Route::get('/forgot-password', [AuthController::class, 'showForgotPasswordForm'])->name('password.request');
    // Throttled so the mail endpoint cannot be hammered for enumeration or
    // mail-bombing; generous enough for legitimate repeated attempts.
    Route::post('/forgot-password', [AuthController::class, 'sendOtp'])->name('password.email')->middleware('throttle:10,1');
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

Route::middleware('auth')->group(function () use ($allProducts, $getCustomProducts, $priceOf, $priceFloat, $resolveProductCategory) {
    Route::get('/dashboard', function () {
        $user = auth()->user();

        // Multi-vendor payment card: sellers see their payout profile status
        // on the dashboard. Only public-facing/masked data is ever sent here.
        $paymentProfile = null;
        if ($user->isSeller()) {
            $paymentProfile = \App\Models\SellerPaymentProfile::where('seller_id', $user->id)->first();
        }

        return view('dashboard', ['paymentProfile' => $paymentProfile]);
    })->name('dashboard');

    Route::get('/home', function () {
        return redirect()->route('home');
    });

    /*
    |----------------------------------------------------------------------
    | NOTE: The old /role/{role} endpoint was removed on purpose.
    | It allowed any authenticated customer to change their own
    | account_type (including promoting themselves to admin) directly from
    | the frontend. account_type is now managed exclusively by the system —
    | customers stay customers, staff accounts are managed in the admin panel.
    |----------------------------------------------------------------------
    */

    Route::get('/products', function () use ($allProducts, $priceOf, $priceFloat) {
        $request = request();
        $catalogService = app(\App\Services\ProductCatalogService::class);
        $products = $allProducts();
        $userRole = auth()->user()->account_type;

        // Categories come from the database, eager-loading their children so
        // the filter dropdown renders without N+1 queries.
        $categories = \App\Models\Category::query()->with('children')->ordered()->get();

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

        // Category filter (combines with the search above and the sort below).
        // The ?category= value is resolved against the database `categories`
        // table first (id, slug or exact name) so filtering always follows
        // the stored category relationship — never the product title.
        $category = trim((string) $request->input('category', ''));
        if ($category !== '') {
            $dbCategory = $catalogService->resolveCategory($category);

            if ($dbCategory) {
                $products = array_filter($products, function ($product) use ($catalogService, $dbCategory) {
                    // Parent categories include their sub-categories' products,
                    // exactly like /categories/{slug} and the home sections:
                    // Electronics also matches Electronics / Mobiles, etc.
                    return $catalogService->productBelongsToCategory($product, $dbCategory, true);
                });
            } else {
                // Unknown category: fall back to an exact stored-name match.
                $categoryKey = mb_strtolower($category);
                $products = array_filter($products, function ($product) use ($categoryKey) {
                    return mb_strtolower(trim((string) ($product['category'] ?? ''))) === $categoryKey;
                });
            }
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
        // A single grouped query covers every product on the page (no N+1).
        if (! empty($pageItems)) {
            $aggregates = \App\Models\Review::approved()
                ->whereIn('product_slug', array_keys($pageItems))
                ->selectRaw('product_slug, AVG(rating) as avg_rating, COUNT(*) as review_count')
                ->groupBy('product_slug')
                ->pluck('avg_rating', 'product_slug');

            $counts = \App\Models\Review::approved()
                ->whereIn('product_slug', array_keys($pageItems))
                ->selectRaw('product_slug, COUNT(*) as review_count')
                ->groupBy('product_slug')
                ->pluck('review_count', 'product_slug');

            foreach ($pageItems as $pSlug => $pItem) {
                $pageItems[$pSlug]['avg_rating']   = (float) ($aggregates[$pSlug] ?? 0);
                $pageItems[$pSlug]['review_count'] = (int) ($counts[$pSlug] ?? 0);
            }
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

        // NEW products may only be filed under active main categories (and
        // their active subcategories) — disabled categories are rejected
        // server-side as well. Eager-loading children avoids N+1 queries.
        $categories = \App\Models\Category::query()
            ->active()
            ->parent()
            ->with(['children' => function ($query) {
                $query->active()->ordered();
            }])
            ->ordered()
            ->get();

        return view('add-product', compact('categories'));
    })->name('products.create');

    Route::post('/products', function () use ($priceFloat, $resolveProductCategory) {
        if (! in_array(auth()->user()->account_type, ['seller', 'admin'])) {
            return redirect()->route('products')->with('error', 'Only sellers or admins can add products.');
        }
        $request = request();

        // seller_id is NEVER accepted from the form/request: the product
        // becomes owned by the authenticated seller (see $row below) or by
        // nobody for admin-created marketplace items. A forged
        // seller_id=another_seller_id input is ignored outright.
        $request->request->remove('seller_id');

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
            'category_id'        => ['nullable', 'integer', 'exists:categories,id'],
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

        // The selected category/subcategory pair is validated against the
        // database categories table (the single source of truth): the main
        // category must exist and be active, and a subcategory must belong
        // to it. The resolved relationship's id + canonical name are stored
        // on the product so home/category sections always follow the real
        // relationship — never a guess from the product name.
        ['selected' => $selectedCategory, 'subcategoryLabel' => $subcategoryLabel] = $resolveProductCategory($data);

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

        // Slug uniqueness is enforced against the products table.
        $counter = 1;
        while (\App\Models\Product::where('slug', $slug)->exists()) {
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
            $image = \App\Services\ProductImageService::defaultImage();
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
        // Clean gallery representation: `image` already holds the main photo,
        // so it must never be stored inside `images` again. Duplicate
        // references / identical uploaded files also collapse here.
        $images = \App\Services\ProductImageService::additionalImages($image, $extraImages);

        $tags = array_values(array_filter(array_map('trim', explode(',', $data['tags'] ?? ''))));

        // Product Options & Variants (OpenCart style). Parsed from the form and
        // stored on the product record; non-variant products simply get empty arrays.
        // The product SKU/slug seeds auto-generated variant SKUs (e.g. S26U-256-BLK).
        $options = [];
        $variants = [];
        if ($request->has('options') && is_array($request->input('options'))) {
            $options = ProductVariantService::normalizeOptions($request->input('options'));
            $variants = ProductVariantService::normalizeVariants(
                $options,
                $request->input('variants', []),
                $data['price'],
                $data['quantity'],
                ($data['sku'] ?? '') ?: strtoupper($slug)
            );
        }

        $row = [
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
            'category'       => $selectedCategory->name,
            'category_name'  => $selectedCategory->name,
            'category_id'    => $selectedCategory->id,
            'subcategory'    => $subcategoryLabel,
            'brand'          => ($data['brand'] ?? '') ?: null,
            'tax'            => (($data['tax'] ?? '') !== null && trim($data['tax'] ?? '') !== '') ? (float) $data['tax'] : 0,
            'status'         => (int) ($data['status'] ?? 1),
            'slug'           => $slug,
            'tags'           => $tags,
            'options'        => $options,
            'variants'       => $variants,
            // Products created by a seller belong to that seller; admin-created
            // products stay unowned (marketplace items).
            'seller_id'      => auth()->user()->account_type === 'seller' ? auth()->id() : null,
        ];

        // Persisted to the products table (with the real category_id FK).
        // A transaction guarantees a failure can never leave a partially
        // written product/variant set behind, and the opening stock is
        // recorded in the inventory history so the first number is always
        // explained.
        $createdProduct = \Illuminate\Support\Facades\DB::transaction(function () use ($slug, $row) {
            $product = app(\App\Services\ProductCatalogService::class)->upsertRow($slug, $row);

            app(\App\Services\InventoryService::class)->recordInitialStock(
                $product->refresh(),
                auth()->user(),
                'Product created',
            );

            return $product;
        });

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

        $categories = \App\Models\Category::query()->with('children')->ordered()->get();

        // INVENTORY (PHASE 3): the availability shown on the product page is
        // resolved by App\Services\InventoryService from the database — the
        // template only renders it. For a variant product this is the
        // PARENT-level view; the per-variant availability below is what the
        // option picker uses once the buyer chooses a combination.
        $stockModel = \App\Models\Product::findBySlug($product);
        $inventory = app(\App\Services\InventoryService::class);

        return view('product-detail', [
            'product' => $products[$product],
            'slug' => $product,
            'customProducts' => $customProducts,
            'categories' => $categories,
            // Single source of truth: unique([main, ...additional]) with
            // equivalent references, byte-identical re-upload copies AND
            // placeholder/default images collapsed, so the page can never
            // render a phantom second image.
            'gallery' => \App\Services\ProductImageService::galleryForDisplay(
                $products[$product]['image'] ?? null,
                $products[$product]['images'] ?? []
            ),
            'availability' => $stockModel ? [
                'label'       => $inventory->storefrontLabel($stockModel),
                'available'   => $inventory->availableFor($stockModel),
                'stock'       => $inventory->stockFor($stockModel),
                'status'      => $inventory->statusFor($stockModel),
                'purchasable' => $inventory->isPurchasable($stockModel),
            ] : null,
            'variantAvailability' => $stockModel ? $inventory->variantAvailability($stockModel) : [],
        ]);
    })
    ->where('product', '[a-zA-Z0-9\-]+')
    ->name('product.show');

    Route::get('/products/{product}/edit', function ($product) use ($allProducts, $getCustomProducts) {
        $products = $allProducts();

        if (! in_array(auth()->user()->account_type, ['seller', 'admin'])) {
            return redirect()->route('products')->with('error', 'Only sellers or admins can edit products.');
        }

        if (! isset($products[$product])) {
            abort(404);
        }

        // PRODUCT OWNERSHIP (server-side authorization): a seller may only
        // open the edit form for a product they own. Other sellers' products
        // and unowned marketplace/seed products are rejected with 403 even
        // when the edit URL is visited directly. Admins keep full access.
        if (auth()->user()->account_type === 'seller'
            && ($products[$product]['seller_id'] ?? null) !== auth()->id()) {
            abort(403, 'You can only manage products you own.');
        }

        $categories = \App\Models\Category::query()->with('children')->ordered()->get();

        return view('edit-product', [
            'product' => $products[$product],
            'slug' => $product,
            'categories' => $categories,
        ]);
    })->name('products.edit');

    Route::post('/products/{product}/update', function ($product) use ($allProducts, $priceFloat, $resolveProductCategory) {
        if (! in_array(auth()->user()->account_type, ['seller', 'admin'])) {
            return redirect()->route('products')->with('error', 'Only sellers or admins can update products.');
        }

        $allProds = $allProducts();
        if (! isset($allProds[$product])) {
            abort(404);
        }

        // PRODUCT OWNERSHIP (server-side authorization): a seller may only
        // update a product they own. A forged/malicious POST against another
        // seller's product (or an unowned seed product) is rejected with 403.
        // Admins keep full product-management access.
        if (auth()->user()->account_type === 'seller'
            && ($allProds[$product]['seller_id'] ?? null) !== auth()->id()) {
            abort(403, 'You can only manage products you own.');
        }

        // seller_id is NEVER accepted from the form/request: ownership is
        // fixed by the record (sellers below re-pin it to their own id, and
        // admin updates simply preserve the existing owner unless the admin
        // explicitly reassigns it through a dedicated admin flow).
        request()->request->remove('seller_id');

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
            'category_id'        => ['nullable', 'integer', 'exists:categories,id'],
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

        // Re-resolve + validate the selected category against the database
        // so moving a product to another category updates the real
        // relationship (the product then automatically leaves its old
        // category sections and appears in the new one). A product may keep
        // its current category even if that category is now disabled, but a
        // disabled category can never be newly selected.
        ['selected' => $selectedCategory, 'subcategoryLabel' => $subcategoryLabel] = $resolveProductCategory(
            $data,
            isset($allProds[$product]['category_id']) && $allProds[$product]['category_id'] !== null
                ? (int) $allProds[$product]['category_id']
                : null
        );

        $customProducts = [];
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
            $image = \App\Services\ProductImageService::defaultImage();
        }

        // Additional images: EXACTLY what this form submission contains.
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
        // The gallery becomes EXACTLY what this form submitted (textarea URLs
        // + freshly uploaded files), cleaned. The edit form prefills the
        // currently stored additional images, so keeping them is an explicit
        // act through the UI - nothing is silently preserved or resurrected
        // server-side, and clearing the field genuinely empties the gallery.
        // The NEW main photo ($image) and any placeholder/default image can
        // never become a stored additional image.
        $images = \App\Services\ProductImageService::additionalImages($image, $extraImages);

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
        //
        // The form always posts the options section (it carries an
        // `options[__present]` marker), so "submitted with no options" and
        // "options left untouched" are distinguishable. When the section is
        // missing altogether the STORED options/variants are kept, so a partial
        // request can never wipe a product's valid variants.
        $options = [];
        $variants = [];
        $hasOptionsPayload = $request->has('options') && is_array($request->input('options'));

        if ($hasOptionsPayload) {
            $options = ProductVariantService::normalizeOptions($request->input('options'));
            $variants = ProductVariantService::normalizeVariants(
                $options,
                $request->input('variants', []),
                $data['price'],
                $data['quantity'],
                ($data['sku'] ?? '') ?: ($allProds[$product]['sku'] ?? strtoupper($product))
            );
        } else {
            $options = $allProds[$product]['options'] ?? [];
            $variants = $allProds[$product]['variants'] ?? [];
        }

        $row = [
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
            'category'       => $selectedCategory->name,
            'category_name'  => $selectedCategory->name,
            'category_id'    => $selectedCategory->id,
            'subcategory'    => $subcategoryLabel,
            'brand'          => ($data['brand'] ?? '') ?: null,
            'tax'            => (($data['tax'] ?? null) !== null && ($data['tax'] ?? '') !== '') ? (float) $data['tax'] : 0,
            'status'         => (int) ($data['status'] ?? 1),
            'slug'           => $slug,
            'tags'           => $tags,
            'options'        => $options,
            'variants'       => $variants,
        ];

        // Ownership integrity: a seller's update re-pins the product to their
        // own account (a no-op for legitimate requests — the ownership check
        // above already verified it — but it makes any accidental drift
        // impossible). Admin updates never touch seller_id here: the record
        // keeps its existing owner unless an admin explicitly reassigns it.
        if (auth()->user()->account_type === 'seller') {
            $row['seller_id'] = auth()->id();
        }

        // Update the existing row in the products table (the real
        // category_id FK is updated, so the product immediately moves to its
        // new category everywhere categories are displayed).
        $productModel = \App\Models\Product::where('slug', $product)->first();

        if (! $productModel) {
            abort(404);
        }

        // Support slug renames without creating a duplicate row.
        if ($slug !== $product && \App\Models\Product::where('slug', $slug)->where('id', '!=', $productModel->id)->exists()) {
            $counter = 1;
            $baseSlug = $slug;
            while (\App\Models\Product::where('slug', $slug)->where('id', '!=', $productModel->id)->exists()) {
                $slug = $baseSlug . '-' . $counter++;
            }
            $row['slug'] = $slug;
        }

        // Transaction: either the whole updated product row lands or nothing
        // does — never a partially-written product + variant set.
        //
        // The variant SET (combinations, SKU, price) is catalogue data and is
        // written with the form. The variant STOCK is deliberately NOT: each
        // variant is carried over with the stock level it already had (0 for a
        // brand new combination) and App\Services\InventoryService then applies
        // the levels the form asked for through setStock(), so every change is
        // locked, validated and recorded as an inventory transaction.
        $previousQuantity = (int) $productModel->quantity;
        $previousVariants = $productModel->variants ?? [];

        $previousStockById = [];

        foreach ($previousVariants as $oldVariant) {
            if (isset($oldVariant['id'])) {
                $previousStockById[(string) $oldVariant['id']] = (int) ($oldVariant['stock'] ?? 0);
            }
        }

        $carriedVariants = array_map(
            fn (array $variant) => array_merge($variant, [
                'stock' => $previousStockById[(string) ($variant['id'] ?? '')] ?? 0,
            ]),
            (array) ($row['variants'] ?? []),
        );

        \Illuminate\Support\Facades\DB::transaction(function () use ($productModel, $row, $previousQuantity, $previousVariants, $carriedVariants): void {
            $productModel->fill(array_merge($row, [
                'quantity' => $previousQuantity,
                'variants' => $carriedVariants,
            ]));
            $productModel->save();

            $inventory = app(\App\Services\InventoryService::class);

            $inventory->applyFormStock(
                $productModel,
                $previousQuantity,
                $previousVariants,
                (int) $row['quantity'],
                (array) ($row['variants'] ?? []),
                auth()->user(),
            );

            // The freshly written levels are what the form asked for, so the
            // model in memory is refreshed from the (authoritative) database.
            $productModel->refresh();
        });
        app(\App\Services\ProductCatalogService::class)->flush();

        return redirect()->route('products')->with('success', 'Product updated successfully.');
    })->name('products.update');

    Route::post('/products/{product}/delete', function ($product) {
        if (! in_array(auth()->user()->account_type, ['seller', 'admin'])) {
            return redirect()->route('products')->with('error', 'Only sellers or admins can remove products.');
        }

        // Seed-catalog products cannot be deleted from the storefront; only
        // admin/seller created products (is_seed = false) are removable.
        $productModel = \App\Models\Product::where('slug', $product)->where('is_seed', false)->first();

        if (! $productModel) {
            abort(404);
        }

        // PRODUCT OWNERSHIP (server-side authorization): a seller may only
        // delete a product they own. Another seller's product is rejected
        // with 403 even on a direct POST to the delete URL. Admins keep
        // their existing authorized deletion access.
        if (auth()->user()->account_type === 'seller'
            && (int) $productModel->seller_id !== (int) auth()->id()) {
            abort(403, 'You can only manage products you own.');
        }

        $productModel->delete();
        app(\App\Services\ProductCatalogService::class)->flush();

        // Remove the deleted product (and its variant lines) from every
        // shopper's persistent cart so stale lines cannot be checked out.
        \App\Services\CartService::forgetProductEverywhere($product);

        return redirect()->route('products')->with('success', 'Product removed successfully.');
    })->name('products.destroy');

    Route::post('/products/{slug}/reviews', [ReviewsController::class, 'store'])
        ->middleware('auth')
        ->name('products.reviews.store');
        // Admin Review / Order / Coupon Management — all require staff access,
        // matching the rest of the admin panel (defense in depth on top of the
        // granular authorization already enforced inside each controller).
        Route::middleware('auth')->group(function () {
            Route::middleware('admin')->group(function () {

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

    // Admin Order Details: status timeline + audit trail + valid actions.
    Route::get('/admin/orders/{order}', [AdminOrdersController::class, 'show'])
        ->name('admin.orders.show');

    Route::post('/admin/orders/{order}/status', [AdminOrdersController::class, 'updateStatus'])
        ->name('admin.orders.status');

    // Admin Order Confirmation (pending -> confirmed; notifies buyer + sellers)
    Route::post('/admin/orders/{order}/approve', [AdminOrdersController::class, 'approve'])
        ->name('admin.orders.approve');

    // Admin Order Cancellation (pending / confirmed -> cancelled)
    Route::post('/admin/orders/{order}/cancel', [AdminOrdersController::class, 'cancel'])
        ->name('admin.orders.cancel');

    // Admin Delivery Partner Assignment (ready_for_pickup -> assigned)
    Route::post('/admin/orders/{order}/assign-delivery', [AdminOrdersController::class, 'assignDelivery'])
        ->name('admin.orders.assign-delivery');

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
        }); // end admin middleware group
    }); // end auth middleware group

    // Admin Order Invoice (Sales > Customers > Order history)
    Route::get('/admin/orders/{order}/invoice', [AdminOrdersController::class, 'invoice'])
        ->middleware('admin')
        ->name('admin.orders.invoice');

    // Admin view of seller payment profiles (read-only; bank account
    // numbers are always rendered masked — see AdminSellerPaymentsController).
    Route::get('/admin/seller-payments', [AdminSellerPaymentsController::class, 'index'])
        ->middleware('admin')
        ->name('admin.seller-payments.index');

    // Admin verification workflow (View / Verify / Reject / Request Update).
    Route::get('/admin/seller-payments/{profile}', [AdminSellerPaymentsController::class, 'show'])
        ->middleware('admin')
        ->name('admin.seller-payments.show');

    Route::post('/admin/seller-payments/{profile}/verify', [AdminSellerPaymentsController::class, 'verify'])
        ->middleware('admin')
        ->name('admin.seller-payments.verify');

    Route::post('/admin/seller-payments/{profile}/reject', [AdminSellerPaymentsController::class, 'reject'])
        ->middleware('admin')
        ->name('admin.seller-payments.reject');

    Route::post('/admin/seller-payments/{profile}/request-update', [AdminSellerPaymentsController::class, 'requestUpdate'])
        ->middleware('admin')
        ->name('admin.seller-payments.request-update');

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

        // INVENTORY (PHASE 3): dashboard cards, filterable inventory table,
        // manual stock adjustment and the full transaction history.
        Route::get('/admin/inventory', [AdminInventoryController::class, 'index'])->name('admin.inventory.index');
        Route::post('/admin/inventory/{product}/adjust', [AdminInventoryController::class, 'adjust'])->middleware('perm:catalog,edit')->name('admin.inventory.adjust');
        Route::get('/admin/inventory-transactions', [AdminInventoryController::class, 'transactions'])->name('admin.inventory.transactions');

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

        // Sales > Returns (buyer-raised return requests + admin workflow)
        Route::get('/admin/returns', [AdminReturnsController::class, 'index'])->name('admin.returns.index');
        Route::get('/admin/returns/create', [AdminReturnsController::class, 'create'])->name('admin.returns.create');
        Route::post('/admin/returns', [AdminReturnsController::class, 'store'])->middleware('perm:sales,create')->name('admin.returns.store');
        Route::get('/admin/returns/{return}', [AdminReturnsController::class, 'show'])->name('admin.returns.show');
        Route::post('/admin/returns/{return}/approve', [AdminReturnsController::class, 'approve'])->middleware('perm:sales,edit')->name('admin.returns.approve');
        Route::post('/admin/returns/{return}/reject', [AdminReturnsController::class, 'reject'])->middleware('perm:sales,edit')->name('admin.returns.reject');
        Route::post('/admin/returns/{return}/pickup', [AdminReturnsController::class, 'schedulePickup'])->middleware('perm:sales,edit')->name('admin.returns.pickup');
        Route::post('/admin/returns/{return}/received', [AdminReturnsController::class, 'markReceived'])->middleware('perm:sales,edit')->name('admin.returns.received');
        // PHASE 3 inventory: record the inspection result of a received
        // return. Only a RESELLABLE product is put back into sellable stock,
        // and the action is idempotent (it can never restock twice).
        Route::post('/admin/returns/{return}/restock', [AdminReturnsController::class, 'restock'])->middleware('perm:sales,edit')->name('admin.returns.restock');
        Route::post('/admin/returns/{return}/refund/start', [AdminReturnsController::class, 'startRefund'])->middleware('perm:sales,edit')->name('admin.returns.refund-start');
        Route::post('/admin/returns/{return}/refund/complete', [AdminReturnsController::class, 'markRefunded'])->middleware('perm:sales,edit')->name('admin.returns.refund-complete');
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

        // Sales > Deliveries (delivery workflow management)
        Route::get('/admin/deliveries', [AdminDeliveriesController::class, 'index'])->name('admin.deliveries.index');
        Route::get('/admin/deliveries/{delivery}', [AdminDeliveriesController::class, 'show'])->name('admin.deliveries.show');
        Route::post('/admin/deliveries/{delivery}/reassign', [AdminDeliveriesController::class, 'reassign'])->name('admin.deliveries.reassign');
        Route::post('/admin/deliveries/{delivery}/status', [AdminDeliveriesController::class, 'status'])->name('admin.deliveries.status');

        // Sales > Delivery Partners (dedicated delivery-partner account management)
        Route::get('/admin/delivery-partners', [AdminDeliveryPartnersController::class, 'index'])->name('admin.delivery-partners.index');
        Route::get('/admin/delivery-partners/create', [AdminDeliveryPartnersController::class, 'create'])->name('admin.delivery-partners.create');
        Route::post('/admin/delivery-partners', [AdminDeliveryPartnersController::class, 'store'])->name('admin.delivery-partners.store');
        Route::get('/admin/delivery-partners/{partner}', [AdminDeliveryPartnersController::class, 'show'])->name('admin.delivery-partners.show');
        Route::get('/admin/delivery-partners/{partner}/edit', [AdminDeliveryPartnersController::class, 'edit'])->name('admin.delivery-partners.edit');
        Route::put('/admin/delivery-partners/{partner}', [AdminDeliveryPartnersController::class, 'update'])->name('admin.delivery-partners.update');
        Route::post('/admin/delivery-partners/{partner}/status', [AdminDeliveryPartnersController::class, 'status'])->name('admin.delivery-partners.status');
    });
    // Wishlist Routes (DB-backed, persistent per user)
    Route::get('/wishlist', [WishlistController::class, 'index'])->name('wishlist.index');
    Route::post('/wishlist/toggle/{product}', [WishlistController::class, 'toggle'])->name('wishlist.toggle');
    Route::post('/wishlist/remove/{product}', [WishlistController::class, 'remove'])->name('wishlist.remove');
    Route::post('/wishlist/to-cart/{product}', [WishlistController::class, 'toCart'])->name('wishlist.to-cart');

    // Order History & Tracking
    Route::get('/orders', [OrdersController::class, 'index'])->name('orders.index');
    Route::get('/orders/{order}', [OrdersController::class, 'show'])->name('orders.show');

    // Buyer cancellation: pending / confirmed orders only (server-side
    // enforced by OrderStatusService; hidden buttons are never the guard).
    Route::post('/orders/{order}/cancel', [OrdersController::class, 'cancel'])->name('orders.cancel');

    /*
    |--------------------------------------------------------------------------
    | Buyer Returns & Refunds
    |--------------------------------------------------------------------------
    | Return requests for delivered order lines inside the configured window.
    | Ownership and every eligibility rule (delivered, window, quantity,
    | duplicates) are re-checked server-side in ReturnsController +
    | ReturnService — the UI never decides.
    */
    Route::get('/returns', [ReturnsController::class, 'index'])->name('returns.index');
    Route::get('/returns/create/{item}', [ReturnsController::class, 'create'])->name('returns.create');
    Route::post('/returns/{item}', [ReturnsController::class, 'store'])->name('returns.store');
    Route::get('/returns/{return}', [ReturnsController::class, 'show'])->name('returns.show');

    /*
    |--------------------------------------------------------------------------
    | Delivery Partner Area
    |--------------------------------------------------------------------------
    | Dedicated delivery dashboard + assigned deliveries. The middleware pair
    | (auth + delivery.partner) enforces the role server-side; the ownership
    | checks in DeliveryController/DeliveryService make sure partners can
    | only ever see and update their own assignments.
    */
    Route::middleware(['auth', 'delivery.partner'])->prefix('delivery')->name('delivery.')->group(function () {
        Route::get('/dashboard', [DeliveryController::class, 'dashboard'])->name('dashboard');

        Route::get('/deliveries', [DeliveryController::class, 'index'])->name('deliveries.index');
        Route::get('/deliveries/{delivery}', [DeliveryController::class, 'show'])->name('deliveries.show');
        Route::post('/deliveries/{delivery}/pickup', [DeliveryController::class, 'pickup'])->name('deliveries.pickup');
        Route::post('/deliveries/{delivery}/out-for-delivery', [DeliveryController::class, 'outForDelivery'])->name('deliveries.out-for-delivery');
        Route::post('/deliveries/{delivery}/delivered', [DeliveryController::class, 'delivered'])->name('deliveries.delivered');

        // Return pickups (Return #RET-xxxx: customer -> partner -> seller).
        Route::get('/returns', [DeliveryController::class, 'pickupsIndex'])->name('pickups.index');
        Route::get('/returns/{pickup}', [DeliveryController::class, 'pickupShow'])->name('pickups.show');
        Route::post('/returns/{pickup}/collect', [DeliveryController::class, 'collect'])->name('pickups.collect');
    });

    /*
    |--------------------------------------------------------------------------
    | Seller Order Workflow
    |--------------------------------------------------------------------------
    | Sellers see orders containing their products and move them through
    | processing / ready-for-pickup. Role enforced server-side.
    */
    Route::middleware(['auth', 'seller'])->prefix('seller')->name('seller.')->group(function () {
        Route::get('/orders', [SellerOrdersController::class, 'index'])->name('orders.index');
        Route::get('/orders/{order}', [SellerOrdersController::class, 'show'])->name('orders.show');
        Route::post('/orders/{order}/status', [SellerOrdersController::class, 'status'])->name('orders.status');

        // Returns raised against this seller's products (read-only view;
        // approval / rejection stays with the admin).
        Route::get('/returns', [SellerReturnsController::class, 'index'])->name('returns.index');
        Route::get('/returns/{return}', [SellerReturnsController::class, 'show'])->name('returns.show');

        // My Products: management list scoped to the authenticated seller
        // (the controller query filters seller_id by the logged-in account).
        Route::get('/products', [SellerProductController::class, 'index'])->name('products.index');

        // INVENTORY (PHASE 3): stock / reserved / available for the seller's
        // OWN products only (the controller query filters seller_id by the
        // logged-in account, and the adjust action re-checks ownership
        // server-side, so a forged request against another seller's product
        // is rejected with 403).
        Route::get('/inventory', [SellerInventoryController::class, 'index'])->name('inventory.index');
        Route::post('/inventory/{product}/adjust', [SellerInventoryController::class, 'adjust'])->name('inventory.adjust');
        Route::get('/inventory-history', [SellerInventoryController::class, 'transactions'])->name('inventory.history');

        // Payment Settings: UPI ID / mobile / QR code / optional bank
        // details. Every route is seller-only and always acts on the
        // authenticated seller's own profile.
        Route::get('/payment-settings', [SellerPaymentSettingsController::class, 'index'])->name('payment-settings.index');
        Route::post('/payment-settings', [SellerPaymentSettingsController::class, 'update'])->name('payment-settings.update');
        Route::post('/payment-settings/qr/remove', [SellerPaymentSettingsController::class, 'removeQr'])->name('payment-settings.qr.remove');
    });

    /*
    |--------------------------------------------------------------------------
    | Staff Area
    |--------------------------------------------------------------------------
    | Dedicated, read-only operations area for the "staff" account type.
    | Staff are NOT admins: the "staff" middleware keeps them out of the
    | admin panel, and no mutation routes exist inside this group.
    */
    Route::middleware(['auth', 'staff'])->prefix('staff')->name('staff.')->group(function () {
        Route::get('/dashboard', [StaffController::class, 'dashboard'])->name('dashboard');
        Route::get('/orders', [StaffController::class, 'orders'])->name('orders.index');
    });

    // In-app notification list (all authenticated roles)
    Route::middleware('auth')->group(function () {
        Route::get('/notifications', [NotificationsController::class, 'index'])->name('notifications.index');
        Route::post('/notifications/read-all', [NotificationsController::class, 'readAll'])->name('notifications.read-all');
        Route::post('/notifications/{notification}/read', [NotificationsController::class, 'read'])->name('notifications.read');
    });

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
