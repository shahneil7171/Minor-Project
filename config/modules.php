<?php

/**
 * KDP MART module registry — PHASE 4.
 *
 * A declarative map of the application's business modules, their services and
 * the features that extend them.
 *
 * WHY THIS EXISTS
 * ---------------
 * The application already has working, tested modules. Rather than build an
 * extension marketplace (which Phase 4 explicitly rules out), this file makes
 * the EXISTING boundaries explicit and machine-readable, so:
 *
 *  - a new feature knows which module it belongs to before it is written;
 *  - controllers stay thin and delegate to the listed service;
 *  - a future plugin system can enumerate capabilities without a second
 *    hand-maintained list that drifts out of date.
 *
 * It is documentation that the code can also read. Nothing in the request
 * path depends on it, so adding a module can never break the store.
 */
return [

    /*
    |---------------------------------------------------------------------------
    | Catalog
    |---------------------------------------------------------------------------
    */
    'catalog' => [
        'label' => 'Catalog',
        'description' => 'Products, categories, subcategories, variants and reviews.',
        'service' => \App\Services\ProductCatalogService::class,
        'variant_service' => \App\Services\ProductVariantService::class,
        'models' => [\App\Models\Product::class, \App\Models\Category::class, \App\Models\Review::class],
        'extensions' => ['recommendations', 'product_views'],
    ],

    /*
    |---------------------------------------------------------------------------
    | Cart & Checkout
    |---------------------------------------------------------------------------
    */
    'cart' => [
        'label' => 'Cart',
        'description' => 'Persistent cart and cart line management.',
        'service' => \App\Services\CartService::class,
        'models' => [\App\Models\Cart::class, \App\Models\CartItem::class],
        'extensions' => ['wishlist'],
    ],

    'checkout' => [
        'label' => 'Checkout',
        'description' => 'Address capture, coupon application and order placement.',
        'service' => \App\Services\OrderWorkflowService::class,
        'extensions' => ['payments', 'shipping', 'tax'],
    ],

    /*
    |---------------------------------------------------------------------------
    | Orders — the lifecycle every other module depends on
    |---------------------------------------------------------------------------
    */
    'orders' => [
        'label' => 'Orders',
        'description' => 'Order lifecycle, status history and the audit trail.',
        'service' => \App\Services\OrderStatusService::class,
        'models' => [\App\Models\Order::class, \App\Models\OrderItem::class, \App\Models\OrderStatusHistory::class],
        'extensions' => ['returns', 'delivery', 'audit_log'],
    ],

    /*
    |---------------------------------------------------------------------------
    | Payments — SIMULATED / MANUAL in this project (no gateway)
    |---------------------------------------------------------------------------
    */
    'payments' => [
        'label' => 'Payments',
        'description' => 'Payment method capture and seller payout profiles. UPI/QR is recorded manually — no gateway is integrated.',
        'service' => \App\Services\CurrencyService::class,
        'models' => [\App\Models\SellerPaymentProfile::class],
        'extensions' => ['financial_reports'],
        'notes' => 'No automatic payment verification exists. Reports must label amounts as recorded, not settled.',
    ],

    'shipping' => [
        'label' => 'Shipping',
        'description' => 'Shipping method selection and the delivery workflow.',
        'service' => \App\Services\DeliveryService::class,
        'models' => [\App\Models\OrderDelivery::class],
        'extensions' => ['delivery_reports'],
    ],

    /*
    |---------------------------------------------------------------------------
    | Returns
    |---------------------------------------------------------------------------
    */
    'returns' => [
        'label' => 'Returns',
        'description' => 'Return requests, approval, pickup, inspection and refunds.',
        'service' => \App\Services\ReturnService::class,
        'models' => [\App\Models\ReturnRequest::class, \App\Models\ReturnStatusHistory::class],
        'policy' => \App\Support\ReturnPolicy::class,
        'extensions' => ['inventory', 'returns_reports'],
    ],

    /*
    |---------------------------------------------------------------------------
    | Inventory
    |---------------------------------------------------------------------------
    */
    'inventory' => [
        'label' => 'Inventory',
        'description' => 'Stock, reserved units, variants and the transaction trail.',
        'service' => \App\Services\InventoryService::class,
        'models' => [\App\Models\InventoryTransaction::class],
        'extensions' => ['inventory_reports', 'low_stock_alerts'],
    ],

    /*
    |---------------------------------------------------------------------------
    | Notifications
    |---------------------------------------------------------------------------
    */
    'notifications' => [
        'label' => 'Notifications',
        'description' => 'In-app alerts and transactional email.',
        'notification' => \App\Notifications\StoreAlert::class,
        'extensions' => ['audit_log'],
    ],

    /*
    |---------------------------------------------------------------------------
    | Reports / Audit / System — PHASE 4
    |---------------------------------------------------------------------------
    */
    'reports' => [
        'label' => 'Reports',
        'description' => 'Admin analytics, sales, products, sellers, customers, returns, inventory and financials.',
        'services' => [
            \App\Services\Reports\DateRangeService::class,
            \App\Services\Reports\SalesReportService::class,
            \App\Services\Reports\ProductReportService::class,
            \App\Services\Reports\SellerReportService::class,
            \App\Services\Reports\ReturnsReportService::class,
            \App\Services\Reports\InventoryReportService::class,
            \App\Services\Reports\ReportExportService::class,
        ],
        'extensions' => ['csv_export'],
        'access' => 'admin',
    ],

    'audit' => [
        'label' => 'Audit',
        'description' => 'Append-only record of administrative actions.',
        'service' => \App\Services\AuditLogService::class,
        'models' => [\App\Models\AuditLog::class],
        'access' => 'admin',
    ],

    'system' => [
        'label' => 'System',
        'description' => 'Health checks, backup readiness, settings, staff users and groups.',
        'services' => [
            \App\Services\SystemHealthService::class,
            \App\Services\BackupReadinessService::class,
        ],
        'access' => 'admin',
    ],

    /*
    |---------------------------------------------------------------------------
    | Identity
    |---------------------------------------------------------------------------
    */
    'users' => [
        'label' => 'Users',
        'description' => 'Accounts, roles, statuses and staff permission groups.',
        'models' => [\App\Models\User::class, \App\Models\UserGroup::class, \App\Models\Address::class],
        'extensions' => ['audit_log'],
    ],

    'sellers' => [
        'label' => 'Sellers',
        'description' => 'Seller accounts, product ownership and payout profiles.',
        'models' => [\App\Models\SellerPaymentProfile::class],
        'extensions' => ['seller_reports'],
        'notes' => 'Seller financial details are restricted to authorized admins and the owning seller.',
    ],

    'delivery' => [
        'label' => 'Delivery',
        'description' => 'Delivery partners, assignments and the return pickup workflow.',
        'service' => \App\Services\DeliveryService::class,
        'models' => [\App\Models\OrderDelivery::class],
        'extensions' => ['delivery_reports'],
    ],

    /*
    |---------------------------------------------------------------------------
    | Localization (Phase 3)
    |---------------------------------------------------------------------------
    */
    'localization' => [
        'label' => 'Localization',
        'description' => 'Currency and language preferences.',
        'services' => [\App\Services\CurrencyService::class, \App\Services\PreferenceService::class],
    ],

];
