<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Review;
use App\Models\User;
use App\Services\ProductCatalogService;
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
            'total_customers' => User::query()->whereNotIn('account_type', ['admin', 'manager'])->count(),
            'revenue'         => (float) Order::where('status', '!=', 'cancelled')->sum('total'),
            'pending_orders'  => Order::where('status', 'pending')->count(),
            'pending_reviews' => Review::where('status', 'pending')->count(),
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

        return view('admin.dashboard', compact('stats', 'orderLabels', 'orderData', 'revLabels', 'revData', 'topProducts'));
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
