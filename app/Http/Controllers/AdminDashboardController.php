<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderDelivery;
use App\Models\OrderItem;
use App\Models\Review;
use App\Models\User;
use App\Services\ProductCatalogService;
use App\Services\Reports\DateRangeService;
use App\Services\Reports\InventoryReportService;
use App\Services\Reports\SellerReportService;
use App\Services\Reports\SalesReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AdminDashboardController extends Controller
{
    /**
     * OpenCart-style admin dashboard: KPI cards and charts.
     */
    public function index(Request $request)
    {
        $this->authorizeAdmin();

        $productCatalog = app(ProductCatalogService::class);

        $stats = [
            'total_products'  => count($productCatalog->all()),
            'total_orders'    => Order::count(),
            'total_customers' => User::query()->whereNotIn('account_type', ['admin', 'manager', 'delivery_partner', 'staff'])->count(),
            'revenue'         => (float) Order::where('status', '!=', 'cancelled')->sum('total'),
            'pending_orders'  => Order::where('status', 'pending')->count(),
            'pending_reviews' => Review::where('status', 'pending')->count(),
        ];

        // Delivery workflow overview (Admin > Deliveries has the full view).
        $deliveryStats = [
            'pending_approval' => Order::where('status', 'pending')->count(),
            'unassigned'       => Order::whereIn('status', ['approved', 'processing', 'packed'])->whereDoesntHave('delivery')->count(),
            'assigned'         => OrderDelivery::whereIn('status', ['assigned', 'ready_for_pickup', 'picked_up'])->count(),
            'out_for_delivery' => OrderDelivery::where('status', 'out_for_delivery')->count(),
            'delivered'        => OrderDelivery::where('status', 'delivered')->count(),
            'failed'           => OrderDelivery::where('status', 'failed')->count(),
        ];

        [$orderLabels, $orderData] = $this->dailySeries(
            Order::where('created_at', '>=', now()->subDays(29)->startOfDay())->get(['created_at']),
            'Orders'
        );

        [$revLabels, $revData] = $this->dailySeries(
            Order::where('status', '!=', 'cancelled')
                ->where('created_at', '>=', now()->subDays(29)->startOfDay())
                ->get(['created_at', 'total']),
            'Revenue',
            true
        );

        $topProducts = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.status', '!=', 'cancelled')
            ->selectRaw('order_items.product_title as title, SUM(order_items.quantity) as qty, SUM(order_items.subtotal) as revenue')
            ->groupBy('order_items.product_title')
            ->orderByDesc('qty')
            ->limit(6)
            ->get();

        return view('admin.dashboard', compact(
            'stats',
            'orderLabels',
            'orderData',
            'revLabels',
            'revData',
            'topProducts',
            'deliveryStats',
        ) + ['analytics' => $this->analytics()]);
    }

    // ==================================================================
    // PHASE 4 — Advanced admin analytics (Part 1)
    //
    // The existing cards above are UNCHANGED. The blocks below ADD the
    // grouped analytics: Sales, Orders, Customers, Sellers and Delivery.
    //
    // Counting rules live in the report services, not here, so a number on
    // the dashboard can never disagree with the same number on a report page.
    // ==================================================================

    /**
     * Grouped analytics for the dashboard panels.
     *
     * Every figure is a database aggregate; nothing loops over orders in PHP.
     *
     * @return array<string, array<string, float|int>>
     */
    private function analytics(): array
    {
        $sales = app(SalesReportService::class);
        $ranges = app(DateRangeService::class);
        $inventory = app(InventoryReportService::class);
        $sellers = app(SellerReportService::class);

        return [
            // SALES — Today / This Week / This Month / This Year
            'sales' => $this->salesPanel($sales, $ranges),

            // ORDERS — one count per lifecycle status, from Order::STATUSES.
            'orders' => $this->countsByStatus(),

            // CUSTOMERS
            'customers' => [
                'total'     => (int) User::query()->where('account_type', 'buyer')->count(),
                'new'       => (int) User::query()->where('account_type', 'buyer')->where('created_at', '>=', now()->startOfMonth())->count(),
                'active'    => (int) User::query()
                    ->where('account_type', 'buyer')
                    ->whereIn('id', fn ($q) => Order::where('status', '!=', 'cancelled')->select('user_id'))
                    ->count(),
                'returning' => (int) User::query()
                    ->where('account_type', 'buyer')
                    ->whereIn('id', function ($q) {
                        $q->select('user_id')->from('orders')->groupBy('user_id')->havingRaw('COUNT(*) > 1');
                    })
                    ->count(),
            ],

            // SELLERS
            'sellers' => $sellers->sellerSummary(),

            // DELIVERY
            'delivery' => $this->deliveryPanel(),

            // INVENTORY (the low-stock alert on the dashboard)
            'inventory' => $inventory->summary(),
        ];
    }

    /**
     * Sales for the four headline windows, each computed server-side.
     *
     * @return array<string, array{revenue: float, orders: int}>
     */
    private function salesPanel(SalesReportService $sales, DateRangeService $ranges): array
    {
        $windows = [
            'today'      => 'today',
            'this_week'  => 'last_7_days',
            'this_month' => 'this_month',
            'this_year'  => 'this_year',
        ];

        $panel = [];

        foreach ($windows as $key => $preset) {
            $overview = $sales->overview($ranges->for($preset));

            $panel[$key] = [
                'revenue' => $overview['revenue'],
                'orders'  => $overview['orders'],
            ];
        }

        return $panel;
    }

    /**
     * Order counts keyed by status, derived from Order::STATUSES so the
     * dashboard can never fall out of step with the lifecycle.
     *
     * @return array<string, int>
     */
    private function countsByStatus(): array
    {
        $counts = Order::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        $panel = [];

        foreach (Order::STATUSES as $status) {
            $panel[$status] = (int) ($counts[$status] ?? 0);
        }

        // "Returned" is not an order status in this architecture — it is a
        // return request — so it is reported separately and never added into
        // the status totals (which would double-count the order).
        $panel['returned'] = (int) \App\Models\ReturnRequest::query()->count();

        return $panel;
    }

    /**
     * Delivery counters for the dashboard.
     *
     * @return array<string, int>
     */
    private function deliveryPanel(): array
    {
        $partners = User::query()
            ->where('account_type', 'delivery_partner')
            ->where('status', 'active')
            ->count();

        $counts = OrderDelivery::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        return [
            'available_partners' => (int) $partners,
            'assigned'           => (int) (($counts['assigned'] ?? 0) + ($counts['ready_for_pickup'] ?? 0) + ($counts['picked_up'] ?? 0)),
            'out_for_delivery'   => (int) ($counts['out_for_delivery'] ?? 0),
            'delivered'          => (int) ($counts['delivered'] ?? 0),
            'failed'             => (int) ($counts['failed'] ?? 0),
        ];
    }

    /**
     * Build a 30 day label/value series, filling missing days with zeros.
     */
    private function dailySeries($rows, string $label, bool $sum = false): array
    {
        $days = collect(range(29, 0))->map(fn ($i) => now()->subDays($i)->format('Y-m-d'));

        $buckets = $rows->groupBy(fn ($row) => Carbon::parse($row->created_at)->format('Y-m-d'));

        $values = $days->map(function ($day) use ($buckets, $sum) {
            $group = $buckets->get($day, collect());

            return $sum
                ? round((float) $group->sum('total'), 2)
                : $group->count();
        });

        return [
            $days->map(fn ($d) => Carbon::parse($d)->format('M d'))->values()->all(),
            $values->values()->all(),
        ];
    }

    private function authorizeAdmin(): void
    {
        abort_unless(auth()->check() && auth()->user()->isStaff(), 403);
    }
}
