<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductView;
use App\Models\User;
use App\Services\Reports\DateRangeService;
use App\Services\Reports\InventoryReportService;
use App\Services\Reports\ProductReportService;
use App\Services\Reports\ReportExportService;
use App\Services\Reports\ReturnsReportService;
use App\Services\Reports\SalesReportService;
use App\Services\Reports\SellerReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminReportsController extends Controller
{
    /**
     * Sales report with daily / weekly / monthly breakdowns.
     */
    public function sales(Request $request)
    {
        $this->authorizeAdmin();

        $period = in_array($request->get('period'), ['daily', 'weekly', 'monthly'], true)
            ? $request->get('period')
            : 'daily';

        [$rows, $labels, $totals] = $this->salesBuckets($period);

        return view('admin.reports.sales', compact('period', 'rows', 'labels', 'totals'));
    }

    public function exportSales(Request $request): StreamedResponse
    {
        $this->authorizeAdmin();

        $period = in_array($request->get('period'), ['daily', 'weekly', 'monthly'], true)
            ? $request->get('period')
            : 'daily';

        [$rows] = $this->salesBuckets($period);

        return $this->csv("sales-report-{$period}.csv", $rows, ['Period', 'Orders', 'Revenue']);
    }

    /**
     * Most viewed products (tracked on the product detail page).
     */
    public function viewed()
    {
        $this->authorizeAdmin();

        $views = ProductView::orderByDesc('views')->limit(100)->get();

        return view('admin.reports.viewed', compact('views'));
    }

    public function exportViewed(): StreamedResponse
    {
        $this->authorizeAdmin();

        $rows = ProductView::orderByDesc('views')->limit(500)->get()
            ->map(fn ($v) => [(string) $v->title, $v->product_slug, $v->views])
            ->all();

        return $this->csv('products-viewed.csv', $rows, ['Product', 'Slug', 'Views']);
    }

    /**
     * Best selling products by quantity (cancelled orders excluded).
     */
    public function purchased()
    {
        $this->authorizeAdmin();

        $products = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.status', '!=', 'cancelled')
            ->selectRaw('order_items.product_slug, order_items.product_title, SUM(order_items.quantity) as qty, SUM(order_items.subtotal) as revenue')
            ->groupBy('order_items.product_slug', 'order_items.product_title')
            ->orderByDesc('qty')
            ->limit(100)
            ->get();

        return view('admin.reports.purchased', compact('products'));
    }

    public function exportPurchased(): StreamedResponse
    {
        $this->authorizeAdmin();

        $rows = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.status', '!=', 'cancelled')
            ->selectRaw('order_items.product_title, order_items.product_slug, SUM(order_items.quantity) as qty, SUM(order_items.subtotal) as revenue')
            ->groupBy('order_items.product_slug', 'order_items.product_title')
            ->orderByDesc('qty')
            ->limit(500)
            ->get()
            ->map(fn ($p) => [$p->product_title, $p->product_slug, $p->qty, number_format((float) $p->revenue, 2)])
            ->all();

        return $this->csv('products-purchased.csv', $rows, ['Product', 'Slug', 'Quantity Sold', 'Revenue']);
    }

    /**
     * Customer reports: highest spending, most orders, newest customers.
     */
    public function customers()
    {
        $this->authorizeAdmin();

        $base = User::query()->whereNotIn('account_type', ['admin', 'manager']);

        $topSpending = (clone $base)
            ->join('orders', 'orders.user_id', '=', 'users.id')
            ->where('orders.status', '!=', 'cancelled')
            ->selectRaw('users.id, users.name, users.email, SUM(orders.total) as spent, COUNT(orders.id) as orders_count')
            ->groupBy('users.id', 'users.name', 'users.email')
            ->orderByDesc('spent')
            ->limit(10)
            ->get();

        $mostOrders = (clone $base)
            ->withCount('orders')
            ->has('orders')
            ->orderByDesc('orders_count')
            ->limit(10)
            ->get();

        $recent = (clone $base)->latest()->limit(10)->get();

        return view('admin.reports.customers', compact('topSpending', 'mostOrders', 'recent'));
    }

    public function exportCustomers(): StreamedResponse
    {
        $this->authorizeAdmin();

        $rows = User::query()
            ->whereNotIn('account_type', ['admin', 'manager'])
            ->leftJoin('orders', function ($join) {
                $join->on('orders.user_id', '=', 'users.id')->where('orders.status', '!=', 'cancelled');
            })
            ->selectRaw('users.name, users.email, COUNT(orders.id) as orders_count, COALESCE(SUM(orders.total), 0) as spent, users.created_at')
            ->groupBy('users.id', 'users.name', 'users.email', 'users.created_at')
            ->orderByDesc('spent')
            ->get()
            ->map(fn ($c) => [
                $c->name,
                $c->email,
                $c->orders_count,
                number_format((float) $c->spent, 2),
                optional($c->created_at)->format('Y-m-d'),
            ])
            ->all();

        return $this->csv('customers-report.csv', $rows, ['Name', 'Email', 'Orders', 'Total Spent', 'Joined']);
    }

    // ==================================================================
    // PHASE 4 — Advanced Analytics
    //
    // Everything below builds on the existing controller and the report
    // services; no business logic lives here. The date window is resolved
    // once by DateRangeService and shared by the page AND its CSV export,
    // so a download always matches what was on screen.
    // ==================================================================

    /**
     * Reports & Analytics — the admin reporting hub (Part 2).
     *
     * Sections: Sales Overview, Orders Overview, Customers, Products,
     * Sellers, Inventory, Returns — plus the date filter that scopes them.
     */
    public function index(Request $request)
    {
        $this->authorizeAdmin();

        $range = app(DateRangeService::class)->resolve($request);
        $sales = app(SalesReportService::class);

        return view('admin.reports.index', [
            'range'           => $range,
            'presets'         => DateRangeService::PRESETS,
            'overview'        => $sales->overview($range),
            'series'          => $sales->salesSeries($range),
            'statusBreakdown' => $sales->orderStatusBreakdown($range),
            'financial'       => $sales->financialSummary($range),
            'payments'        => $sales->paymentOverview($range),
            'hasGateway'      => $sales->hasPaymentGateway(),
            'customers'       => $sales->customerAnalytics($range),
            'aov'             => $sales->aovAcrossPeriods(),
            'topProducts'     => app(ProductReportService::class)->topProducts($range, 'units', 10),
            'categories'      => app(ProductReportService::class)->categoryPerformance($range, 10),
            'sellers'         => app(SellerReportService::class)->sellerPerformance($range, 10, 'revenue'),
            'returns'         => app(ReturnsReportService::class)->summary($range),
            'inventory'       => app(InventoryReportService::class)->summary(),
        ]);
    }

    /**
     * Top Selling Products + Product Performance + Category Performance
     * (Parts 3, 4 and 5).
     */
    public function products(Request $request)
    {
        $this->authorizeAdmin();

        $range = app(DateRangeService::class)->resolve($request);
        $sort = in_array($request->get('sort'), ['units', 'revenue'], true) ? $request->get('sort') : 'units';
        $products = app(ProductReportService::class);

        return view('admin.reports.products', [
            'range'       => $range,
            'presets'     => DateRangeService::PRESETS,
            'sort'        => $sort,
            'sortOptions' => ProductReportService::SORT_OPTIONS,
            'topProducts' => $products->topProducts($range, $sort),
            'neverSold'   => $products->neverSold($range, 50),
            'performance' => $products->productPerformance($range),
            'categories'  => $products->categoryPerformance($range),
        ]);
    }

    /**
     * Seller Performance (Part 6). Commercial data only — seller payment
     * credentials are never selected by the report service.
     */
    public function sellers(Request $request)
    {
        $this->authorizeAdmin();

        $range = app(DateRangeService::class)->resolve($request);
        $sort = in_array($request->get('sort'), ['units', 'orders', 'products', 'rating', 'revenue'], true)
            ? $request->get('sort')
            : 'revenue';

        return view('admin.reports.sellers', [
            'range'   => $range,
            'presets' => DateRangeService::PRESETS,
            'sort'    => $sort,
            'sellers' => app(SellerReportService::class)->sellerPerformance($range, 100, $sort),
        ]);
    }

    /**
     * Drill into one seller's performance. The account MUST be a seller, and
     * no payment/credential column is ever exposed on this page.
     */
    public function seller(Request $request, User $seller)
    {
        $this->authorizeAdmin();

        abort_unless($seller->isSeller(), 404);

        return view('admin.reports.seller', [
            'range'       => app(DateRangeService::class)->resolve($request),
            'presets'     => DateRangeService::PRESETS,
            'seller'      => $seller,
            'performance' => app(SellerReportService::class)->sellerDetail(
                $seller,
                app(DateRangeService::class)->resolve($request)
            ),
        ]);
    }

    /**
     * Inventory Intelligence (Part 7).
     */
    public function inventory(Request $request)
    {
        $this->authorizeAdmin();

        $range = app(DateRangeService::class)->resolve($request);
        $inventory = app(InventoryReportService::class);

        return view('admin.reports.inventory', [
            'range'     => $range,
            'presets'   => DateRangeService::PRESETS,
            'summary'   => $inventory->summary(),
            'sections'  => $inventory->inventoryReport($range),
            'threshold' => $inventory->storeThreshold(),
        ]);
    }

    /**
     * Returns Analytics (Part 8).
     */
    public function returns(Request $request)
    {
        $this->authorizeAdmin();

        $range = app(DateRangeService::class)->resolve($request);
        $returns = app(ReturnsReportService::class);

        return view('admin.reports.returns', [
            'range'       => $range,
            'presets'     => DateRangeService::PRESETS,
            'summary'     => $returns->summary($range),
            'breakdown'   => $returns->statusBreakdown($range),
            'topProducts' => $returns->topReturnedProducts($range),
            'byCategory'  => $returns->returnsByCategory($range),
            'recent'      => $returns->recent($range),
        ]);
    }

    /**
     * Financial Summary + Payment Overview (Parts 11 and 12).
     */
    public function financial(Request $request)
    {
        $this->authorizeAdmin();

        $range = app(DateRangeService::class)->resolve($request);
        $sales = app(SalesReportService::class);

        return view('admin.reports.financial', [
            'range'      => $range,
            'presets'    => DateRangeService::PRESETS,
            'financial'  => $sales->financialSummary($range),
            'payments'   => $sales->paymentOverview($range),
            'hasGateway' => $sales->hasPaymentGateway(),
            'overview'   => $sales->overview($range),
        ]);
    }

    // ==================================================================
    // PHASE 4 — CSV exports
    //
    // Every export resolves the SAME date window as its page, so what is
    // downloaded is exactly what was reviewed. Rows are streamed and built
    // from an explicit column whitelist — no password, reset token or seller
    // payment field can ever appear in a spreadsheet.
    // ==================================================================

    /**
     * Detailed sales report (order line level) — Part 13.
     */
    public function exportSalesDetail(Request $request): StreamedResponse
    {
        $this->authorizeAdmin();

        $range = app(DateRangeService::class)->resolve($request);

        return app(ReportExportService::class)->exportSalesLines($range);
    }

    /**
     * Top selling products export.
     */
    public function exportProducts(Request $request): StreamedResponse
    {
        $this->authorizeAdmin();

        $range = app(DateRangeService::class)->resolve($request);
        $sort = in_array($request->get('sort'), ['units', 'revenue'], true) ? $request->get('sort') : 'units';

        $rows = app(ProductReportService::class)
            ->topProducts($range, $sort, ReportExportService::MAX_EXPORT_ROWS)
            ->map(fn ($p) => [
                $p->product_title,
                $p->product_slug,
                $p->sku,
                $p->category,
                $p->units,
                $p->orders,
                number_format((float) $p->revenue, 2, '.', ''),
            ])
            ->all();

        return app(ReportExportService::class)->csv(
            app(ReportExportService::class)->filename('top-products', $range),
            ['Product', 'Slug', 'SKU', 'Category', 'Units Sold', 'Orders', 'Revenue'],
            $rows
        );
    }

    /**
     * Seller performance export (commercial columns only).
     */
    public function exportSellers(Request $request): StreamedResponse
    {
        $this->authorizeAdmin();

        $range = app(DateRangeService::class)->resolve($request);

        $rows = app(SellerReportService::class)
            ->sellerPerformance($range, ReportExportService::MAX_EXPORT_ROWS)
            ->map(fn ($s) => [
                $s->name,
                $s->email,
                $s->products,
                $s->orders,
                $s->units_sold,
                number_format((float) $s->revenue, 2, '.', ''),
                $s->returns,
                $s->avg_rating,
            ])
            ->all();

        return app(ReportExportService::class)->csv(
            app(ReportExportService::class)->filename('seller-performance', $range),
            ['Seller', 'Email', 'Products', 'Orders', 'Units Sold', 'Gross Sales', 'Returns', 'Average Rating'],
            $rows
        );
    }

    /**
     * Inventory report export (low stock rows only — the actionable ones).
     */
    public function exportInventory(Request $request): StreamedResponse
    {
        $this->authorizeAdmin();

        $range = app(DateRangeService::class)->resolve($request);
        $lowStock = app(InventoryReportService::class)->inventoryReport($range)['low_stock'];

        $rows = $lowStock->map(fn ($p) => [
            $p->title,
            $p->sku,
            $p->seller,
            $p->stock,
            $p->available,
            $p->threshold,
            $p->units_sold,
            $p->status_label,
        ])->all();

        return app(ReportExportService::class)->csv(
            app(ReportExportService::class)->filename('low-stock', $range),
            ['Product', 'SKU', 'Seller', 'Stock', 'Available', 'Threshold', 'Sold', 'Status'],
            $rows
        );
    }

    /**
     * Returns analytics export.
     */
    public function exportReturns(Request $request): StreamedResponse
    {
        $this->authorizeAdmin();

        $range = app(DateRangeService::class)->resolve($request);

        $rows = app(ReturnsReportService::class)
            ->topReturnedProducts($range, ReportExportService::MAX_EXPORT_ROWS)
            ->map(fn ($r) => [
                $r->product_title,
                $r->product_slug,
                $r->requests,
                $r->units,
                number_format((float) $r->refund_amount, 2, '.', ''),
            ])
            ->all();

        return app(ReportExportService::class)->csv(
            app(ReportExportService::class)->filename('returns', $range),
            ['Product', 'Slug', 'Requests', 'Units Returned', 'Refund Amount'],
            $rows
        );
    }

    /**
     * Bucket paid orders into daily / weekly / monthly rows.
     *
     * @return array{0: array<int, array>, 1: array<int, string>, 2: array{orders: int, revenue: float, chart: array<int, float>}}
     */
    private function salesBuckets(string $period): array
    {
        $query = Order::where('status', '!=', 'cancelled');

        if ($period === 'daily') {
            $query->where('created_at', '>=', now()->subDays(29)->startOfDay());
            $format = 'Y-m-d';
            $label = 'M d, Y';
            $cursor = now()->copy()->subDays(29)->startOfDay();
            $step = fn (Carbon $date) => $date->addDay();
        } elseif ($period === 'weekly') {
            $query->where('created_at', '>=', now()->subWeeks(11)->startOfWeek());
            $format = 'o-W';
            $label = '"W"W o';
            $cursor = now()->copy()->subWeeks(11)->startOfWeek();
            $step = fn (Carbon $date) => $date->addWeek();
        } else {
            $query->where('created_at', '>=', now()->subMonths(11)->startOfMonth());
            $format = 'Y-m';
            $label = 'M Y';
            $cursor = now()->copy()->subMonths(11)->startOfMonth();
            $step = fn (Carbon $date) => $date->addMonth();
        }

        $orders = $query->get(['created_at', 'total']);

        // Build every bucket in range so empty periods still show as zero.
        $buckets = [];
        $end = now();

        while ($cursor->lessThan($end)) {
            $buckets[$cursor->format($format)] = ['label' => $cursor->format($label), 'orders' => 0, 'revenue' => 0.0];
            $step($cursor);
        }

        foreach ($orders as $order) {
            $key = Carbon::parse($order->created_at)->format($format);

            if (! isset($buckets[$key])) {
                continue;
            }

            $buckets[$key]['orders']++;
            $buckets[$key]['revenue'] += (float) $order->total;
        }

        $rows = collect($buckets)->map(fn ($bucket) => [
            $bucket['label'],
            $bucket['orders'],
            number_format($bucket['revenue'], 2),
        ])->values()->all();

        $labels = collect($buckets)->pluck('label')->values()->all();

        return [
            $rows,
            $labels,
            [
                'orders' => array_sum(array_column($buckets, 'orders')),
                'revenue' => round(array_sum(array_column($buckets, 'revenue')), 2),
                'chart' => array_map(fn ($r) => (float) str_replace(',', '', $r[2]), $rows),
            ],
        ];
    }

    /**
     * Stream a CSV download.
     */
    private function csv(string $filename, array $rows, array $header): StreamedResponse
    {
        return response()->streamDownload(function () use ($rows, $header) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $header);

            foreach ($rows as $row) {
                fputcsv($out, $row);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    private function authorizeAdmin(): void
    {
        abort_unless(auth()->check() && auth()->user()->isStaff(), 403);
    }
}