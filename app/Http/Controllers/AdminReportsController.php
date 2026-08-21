<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductView;
use App\Models\User;
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