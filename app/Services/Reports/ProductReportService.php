<?php

namespace App\Services\Reports;

use App\Models\Category;
use App\Models\Product;
use App\Models\ReturnRequest;
use App\Models\Review;
use Illuminate\Support\Facades\DB;

/**
 * ProductReportService — PHASE 4.
 *
 * Top-selling products, per-product performance and category performance.
 *
 * KEY DESIGN DECISIONS
 * --------------------
 * 1. ZERO-SALES PRODUCTS ARE EXCLUDED from the "Top Selling" list — a product
 *    that has never sold is not a top seller. They are available separately
 *    through neverSold(), so the UI can show them under their own heading
 *    instead of padding the ranking with zeroes.
 * 2. LINE-LEVEL AGGREGATION. Units/revenue come from `order_items`, grouped
 *    by `product_id` (falling back to the slug snapshot for legacy rows), so
 *    the report works even after a product is renamed.
 * 3. VIEWS ARE NOT INVENTED. ProductView tracking already exists and is
 *    aggregated into a single row per product, so real view counts are
 *    joined in. No historical numbers are fabricated.
 * 4. CATEGORIES COME FROM THE DATABASE. Category performance groups by
 *    products.category_id, so whatever categories exist are reported.
 */
class ProductReportService
{
    /**
     * Sorting options exposed to the admin (Part 3).
     *
     * @var array<string, string>
     */
    public const SORT_OPTIONS = [
        'units'   => 'Units Sold',
        'revenue' => 'Revenue',
    ];

    /**
     * Cap on rows returned to the browser (exports use a larger cap).
     */
    public const PAGE_LIMIT = 100;

    public const EXPORT_LIMIT = 5000;

    /**
     * Top selling products in the window.
     *
     * Cancelled orders are excluded (nothing was sold), matching
     * SalesReportService::REVENUE_STATUSES.
     *
     * @return \Illuminate\Support\Collection<int, object>
     */
    public function topProducts(DateRange $range, string $sort = 'units', int $limit = self::PAGE_LIMIT)
    {
        $sort = array_key_exists($sort, self::SORT_OPTIONS) ? $sort : 'units';

        // Whitelisted ORDER BY — the sort key can never reach SQL raw.
        $orderColumn = $sort === 'revenue' ? 'revenue' : 'units';

        return DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->leftJoin('products', 'products.id', '=', 'order_items.product_id')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->whereIn('orders.status', SalesReportService::REVENUE_STATUSES)
            ->whereBetween('orders.created_at', [$range->from, $range->to])
            ->groupBy('order_items.product_slug')
            ->orderByDesc($orderColumn)
            ->limit($limit)
            ->select([
                'order_items.product_slug',
                DB::raw('MAX(order_items.product_title) as product_title'),
                DB::raw('COALESCE(MAX(products.sku), MAX(order_items.sku)) as sku'),
                DB::raw('MAX(COALESCE(categories.name, products.category_name)) as category'),
                DB::raw('SUM(order_items.quantity) as units'),
                DB::raw('COUNT(DISTINCT order_items.order_id) as orders'),
                DB::raw('SUM(order_items.subtotal) as revenue'),
            ])
            ->get();
    }

    /**
     * Products that have NEVER sold (kept separate from the top list).
     *
     * @return \Illuminate\Support\Collection<int, object>
     */
    public function neverSold(DateRange $range, int $limit = self::PAGE_LIMIT)
    {
        return DB::table('products')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->whereNotExists(function ($q) use ($range) {
                $q->select(DB::raw(1))
                    ->from('order_items')
                    ->join('orders', 'orders.id', '=', 'order_items.order_id')
                    ->whereColumn('order_items.product_id', 'products.id')
                    ->whereIn('orders.status', SalesReportService::REVENUE_STATUSES)
                    ->whereBetween('orders.created_at', [$range->from, $range->to]);
            })
            ->orderByDesc('products.id')
            ->limit($limit)
            ->select([
                'products.id',
                'products.slug',
                'products.title',
                'products.sku',
                DB::raw('MAX(COALESCE(categories.name, products.category_name)) as category'),
            ])
            ->get();
    }

    /**
     * Reusable derived table: per-product sales aggregates for the window.
     *
     * A single GROUP BY sub-select joined onto the products table beats N
     * correlated sub-queries: the database aggregates once instead of once
     * per product row.
     */
    private function salesAggregate(DateRange $range)
    {
        return DB::table('order_items')
            ->select([
                'order_items.product_id',
                DB::raw('SUM(order_items.quantity) as units'),
                DB::raw('COUNT(DISTINCT order_items.order_id) as orders_count'),
                DB::raw('SUM(order_items.subtotal) as revenue'),
            ])
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereIn('orders.status', SalesReportService::REVENUE_STATUSES)
            ->whereBetween('orders.created_at', [$range->from, $range->to])
            ->whereNotNull('order_items.product_id')
            ->groupBy('order_items.product_id');
    }

    /**
     * Reusable derived table: per-product return counts (all time).
     */
    private function returnAggregate()
    {
        return DB::table('returns')
            ->select([
                'returns.product_id',
                DB::raw('COUNT(*) as return_count'),
            ])
            ->whereNotNull('returns.product_id')
            ->groupBy('returns.product_id');
    }

    /**
     * Reusable derived table: per-product review counts + average rating.
     */
    private function reviewAggregate()
    {
        return DB::table('reviews')
            ->select([
                'reviews.product_slug',
                DB::raw('COUNT(*) as review_count'),
                DB::raw('AVG(reviews.rating) as avg_rating'),
            ])
            ->groupBy('reviews.product_slug');
    }


    // ------------------------------------------------------------------
    // Product performance
    // ------------------------------------------------------------------

    /**
     * Per-product performance: sales, views, stock, returns and reviews.
     *
     * VIEWS COME FROM THE EXISTING `product_views` aggregate (one row per
     * slug, incremented on the product page). A product never viewed reports
     * 0 — no historical view number is ever invented.
     *
     * The listing includes products that sold in the window OR that have
     * recorded views, so the admin sees both commercial and interest signals.
     * Every metric is a LEFT JOINed derived aggregate: one pass over
     * order_items/returns/reviews, not one query per product.
     *
     * @return \Illuminate\Support\Collection<int, object>
     */
    public function productPerformance(DateRange $range, int $limit = self::PAGE_LIMIT, string $sort = 'revenue')
    {
        $orderColumn = match ($sort) {
            'units'  => 'units_sold',
            'stock'  => 'stock',
            'rating' => 'avg_rating',
            'views'  => 'views',
            default  => 'revenue',
        };

        $sales = $this->salesAggregate($range);
        $returns = $this->returnAggregate();
        $reviews = $this->reviewAggregate();

        return DB::table('products')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->leftJoin('product_views', 'product_views.product_slug', '=', 'products.slug')
            ->leftJoin($sales, 'sales.product_id', '=', 'products.id')
            ->leftJoin($returns, 'returns_agg.product_id', '=', 'products.id')
            ->leftJoin($reviews, 'reviews_agg.product_slug', '=', 'products.slug')
            ->where(function ($q) {
                $q->whereNotNull('sales.product_id')->orWhere('product_views.views', '>', 0);
            })
            ->orderByDesc($orderColumn)
            ->limit($limit)
            ->select([
                'products.id',
                'products.slug',
                'products.title',
                'products.sku',
                'products.quantity',
                'products.reserved',
                'products.stock_status',
                DB::raw('COALESCE(categories.name, products.category_name) as category'),
                DB::raw('COALESCE(product_views.views, 0) as views'),
                DB::raw('COALESCE(sales.units, 0) as units_sold'),
                DB::raw('COALESCE(sales.orders_count, 0) as orders_count'),
                DB::raw('COALESCE(sales.revenue, 0) as revenue'),
                DB::raw('COALESCE(returns_agg.return_count, 0) as return_count'),
                DB::raw('COALESCE(reviews_agg.review_count, 0) as review_count'),
                DB::raw('COALESCE(reviews_agg.avg_rating, 0) as avg_rating'),
            ])
            ->get()
            ->map(function ($row) {
                // stock mirrors the storefront definition: stock - reserved.
                $row->stock = max(0, (int) $row->quantity - (int) $row->reserved);
                $row->avg_rating = round((float) $row->avg_rating, 2);
                $row->revenue = round((float) $row->revenue, 2);

                return $row;
            });
    }

    // ------------------------------------------------------------------
    // Category performance
    // ------------------------------------------------------------------

    /**
     * Performance per category, driven by the categories actually in the DB.
     *
     * Subcategory products roll up into their PARENT category, so the number
     * an admin sees for "Electronics" includes its subcategories. Categories
     * with no sales are still listed (with zeroes) so the report mirrors the
     * real catalog instead of only what happened to sell.
     *
     * @return \Illuminate\Support\Collection<int, object>
     */
    public function categoryPerformance(DateRange $range, int $limit = self::PAGE_LIMIT)
    {
        $sales = $this->salesAggregate($range);

        return DB::table('categories as parent')
            // Children roll up into their parent row.
            ->leftJoin('categories as child', 'child.parent_id', '=', 'parent.id')
            ->leftJoin('products', function ($join) {
                $join->on('products.category_id', '=', 'parent.id')
                    ->orOn('products.category_id', '=', 'child.id');
            })
            ->leftJoin($sales, 'sales.product_id', '=', 'products.id')
            ->whereNull('parent.parent_id')
            ->groupBy('parent.id', 'parent.name', 'parent.sort_order')
            ->orderByDesc('revenue')
            ->orderBy('parent.sort_order')
            ->limit($limit)
            ->select([
                'parent.id',
                'parent.name',
                DB::raw('COUNT(DISTINCT products.id) as products'),
                DB::raw('COALESCE(SUM(sales.units), 0) as units_sold'),
                DB::raw('COALESCE(SUM(sales.orders_count), 0) as orders_count'),
                DB::raw('COALESCE(SUM(sales.revenue), 0) as revenue'),
            ])
            ->get()
            ->map(fn ($row) => (object) [
                'id'         => $row->id,
                'name'       => $row->name,
                'products'   => (int) $row->products,
                'units_sold' => (int) $row->units_sold,
                'orders'     => (int) $row->orders_count,
                'revenue'    => round((float) $row->revenue, 2),
            ]);
    }
}

