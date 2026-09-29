<?php

namespace App\Services\Reports;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * SellerReportService — PHASE 4.
 *
 * Per-seller commercial performance for the ADMIN report.
 *
 * SECURITY (critical, Part 6)
 * ---------------------------
 * This service NEVER touches `seller_payment_profiles`. No UPI id, no QR
 * path, no bank account number, no IFSC and no admin note can leak into a
 * report, because no column from that table is ever selected here. Seller
 * financials are reachable only through the dedicated, permission-guarded
 * Admin > Seller Payments pages that already existed before Phase 4.
 *
 * Aggregation is line-level (order_items.seller_id), so a multi-seller order
 * contributes only to the seller whose products it actually contained.
 */
class SellerReportService
{
    public const PAGE_LIMIT = 100;

    public const EXPORT_LIMIT = 5000;

    /**
     * Seller performance rows for the window.
     *
     * @return \Illuminate\Support\Collection<int, object>
     */
    public function sellerPerformance(DateRange $range, int $limit = self::PAGE_LIMIT, string $sort = 'revenue')
    {
        $orderColumn = match ($sort) {
            'units'   => 'units_sold',
            'orders'  => 'orders_count',
            'products'=> 'products',
            'rating'  => 'avg_rating',
            default   => 'revenue',
        };

        // Sales aggregate for the window, already scoped to seller lines.
        $sales = DB::table('order_items')
            ->select([
                'order_items.seller_id',
                DB::raw('SUM(order_items.quantity) as units'),
                DB::raw('COUNT(DISTINCT order_items.order_id) as orders_count'),
                DB::raw('SUM(order_items.subtotal) as revenue'),
            ])
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereIn('orders.status', SalesReportService::REVENUE_STATUSES)
            ->whereBetween('orders.created_at', [$range->from, $range->to])
            ->whereNotNull('order_items.seller_id')
            ->groupBy('order_items.seller_id');

        // Returns against this seller's products (all time).
        $returns = DB::table('returns')
            ->select([
                'returns.seller_id',
                DB::raw('COUNT(*) as return_count'),
            ])
            ->whereNotNull('returns.seller_id')
            ->groupBy('returns.seller_id');

        // Reviews left for this seller's products (products -> slug).
        $ratings = DB::table('reviews')
            ->join('products', 'products.slug', '=', 'reviews.product_slug')
            ->select([
                'products.seller_id',
                DB::raw('AVG(reviews.rating) as avg_rating'),
                DB::raw('COUNT(*) as review_count'),
            ])
            ->whereNotNull('products.seller_id')
            ->groupBy('products.seller_id');

        return DB::table('users')
            ->leftJoin($sales, 'sales.seller_id', '=', 'users.id')
            ->leftJoin($returns, 'returns_agg.seller_id', '=', 'users.id')
            ->leftJoin($ratings, 'ratings.seller_id', '=', 'users.id')
            // Product count from the catalog, kept as a scalar sub-select so
            // it cannot multiply the joined aggregates.
            ->where('users.account_type', 'seller')
            ->orderByDesc($orderColumn)
            ->limit($limit)
            ->select([
                'users.id',
                'users.name',
                'users.email',
                'users.status',
                DB::raw('(SELECT COUNT(*) FROM products p WHERE p.seller_id = users.id) as products'),
                DB::raw('COALESCE(sales.orders_count, 0) as orders_count'),
                DB::raw('COALESCE(sales.units, 0) as units_sold'),
                DB::raw('COALESCE(sales.revenue, 0) as revenue'),
                DB::raw('COALESCE(returns_agg.return_count, 0) as return_count'),
                DB::raw('ROUND(COALESCE(ratings.avg_rating, 0), 2) as avg_rating'),
            ])
            ->get()
            ->map(fn ($row) => (object) [
                'id'         => $row->id,
                'name'       => $row->name,
                'email'      => $row->email,
                'status'     => $row->status,
                'products'   => (int) $row->products,
                'orders'     => (int) $row->orders_count,
                'units_sold' => (int) $row->units_sold,
                'revenue'    => round((float) $row->revenue, 2),
                'returns'    => (int) $row->return_count,
                'avg_rating' => round((float) $row->avg_rating, 2),
            ]);
    }

    /**
     * Headline seller counters for the admin dashboard.
     *
     * @return array{total: int, active: int, inactive: int, top: \Illuminate\Support\Collection}
     */
    public function sellerSummary(): array
    {
        $base = User::query()->where('account_type', 'seller');

        return [
            'total'    => (int) (clone $base)->count(),
            'active'   => (int) (clone $base)->where('status', 'active')->count(),
            'inactive' => (int) (clone $base)->whereIn('status', ['inactive', 'blocked'])->count(),
            'top'      => $this->topSellers(),
        ];
    }

    /**
     * The best-performing sellers, all time (dashboard "Top Sellers").
     *
     * @return \Illuminate\Support\Collection<int, object>
     */
    public function topSellers(int $limit = 5)
    {
        return DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('users', 'users.id', '=', 'order_items.seller_id')
            ->whereIn('orders.status', SalesReportService::REVENUE_STATUSES)
            ->whereNotNull('order_items.seller_id')
            ->groupBy('order_items.seller_id', 'users.name')
            ->orderByDesc('revenue')
            ->limit($limit)
            ->select([
                'users.id',
                'users.name',
                DB::raw('SUM(order_items.quantity) as units'),
                DB::raw('SUM(order_items.subtotal) as revenue'),
            ])
            ->get();
    }

    /**
     * A single seller's performance detail (admin drill-down).
     *
     * Still no payment/credential column is selected — the admin inspects a
     * seller's COMMERCIAL performance here and their payout profile on the
     * separate, permission-guarded Seller Payments page.
     *
     * @return array<string, mixed>
     */
    public function sellerDetail(User $seller, DateRange $range): array
    {
        $row = $this->sellerPerformance($range, self::EXPORT_LIMIT)
            ->firstWhere('id', $seller->id);

        return [
            'seller'      => $seller,
            'performance' => $row,
            'topProducts' => $this->topProductsForSeller($seller, $range),
        ];
    }

    /**
     * Best sellers' products for the drill-down page.
     *
     * @return \Illuminate\Support\Collection<int, object>
     */
    public function topProductsForSeller(User $seller, DateRange $range, int $limit = 10)
    {
        return DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('order_items.seller_id', $seller->id)
            ->whereIn('orders.status', SalesReportService::REVENUE_STATUSES)
            ->whereBetween('orders.created_at', [$range->from, $range->to])
            ->groupBy('order_items.product_slug')
            ->orderByDesc('units')
            ->limit($limit)
            ->select([
                'order_items.product_slug',
                DB::raw('MAX(order_items.product_title) as product_title'),
                DB::raw('SUM(order_items.quantity) as units'),
                DB::raw('SUM(order_items.subtotal) as revenue'),
            ])
            ->get();
    }
}
