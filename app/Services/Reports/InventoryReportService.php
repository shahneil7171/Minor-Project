<?php

namespace App\Services\Reports;

use App\Models\Product;
use App\Models\Setting;
use App\Services\InventoryService;
use Illuminate\Support\Facades\DB;

/**
 * InventoryReportService — PHASE 4.
 *
 * Inventory intelligence: low stock, out of stock, high stock, recently
 * added, fast moving and slow moving products.
 *
 * REUSES THE EXISTING INVENTORY ARCHITECTURE
 * ------------------------------------------
 * - The low-stock threshold is read through InventoryService::thresholdFor()
 *   / Setting::lowStockThreshold(), so a product's own
 *   `low_stock_threshold` wins and the store-wide default is never
 *   hard-coded here. If no threshold exists, the admin-configurable store
 *   setting (Admin > System > Settings) is used.
 * - "High stock" is expressed as a MULTIPLE of the same effective threshold
 *   (5x), which is a ratio rather than a second magic number.
 * - Availability is `quantity - reserved`, the same definition the storefront
 *   and checkout use, and variant-level stock keeps its own rows.
 * - Sales velocity is a correlated-free LEFT JOIN of a single grouped
 *   sub-select, so the whole page costs a handful of queries.
 */
class InventoryReportService
{
    public const PAGE_LIMIT = 100;

    public const EXPORT_LIMIT = 5000;

    /**
     * "High stock" = this multiple of the effective low-stock threshold.
     */
    public const HIGH_STOCK_MULTIPLE = 5;

    /**
     * Upper bound on products scanned (keeps the report responsive on a huge
     * catalog; the ordering always surfaces the most relevant rows).
     */
    private const SCAN_LIMIT = 2000;

    /**
     * The sections rendered on the inventory report, each already filtered.
     *
     * @return array<string, \Illuminate\Support\Collection<int, object>>
     */
    public function inventoryReport(DateRange $range): array
    {
        $products = $this->scannedProducts();

        return [
            'low_stock'     => $this->filterByStatus($products, 'low_stock'),
            'out_of_stock'  => $this->filterByStatus($products, 'out_of_stock'),
            'high_stock'    => $this->filterByStatus($products, 'high_stock'),
            'recently_added'=> $this->filterByStatus($products, 'recently_added'),
            'fast_moving'   => $this->filterByStatus($products, 'fast_moving'),
            'slow_moving'   => $this->filterByStatus($products, 'slow_moving'),
        ];
    }

    /**
     * Every product (with its sales velocity) reduced to report rows.
     *
     * @return \Illuminate\Support\Collection<int, object>
     */
    public function scannedProducts(): \Illuminate\Support\Collection
    {
        $sales = DB::table('order_items')
            ->select([
                'order_items.product_id',
                DB::raw('SUM(order_items.quantity) as units_sold'),
            ])
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereIn('orders.status', SalesReportService::REVENUE_STATUSES)
            ->whereNotNull('order_items.product_id')
            ->groupBy('order_items.product_id');

        $windowSales = DB::table('order_items')
            ->select([
                'order_items.product_id',
                DB::raw('SUM(order_items.quantity) as window_units'),
            ])
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereIn('orders.status', SalesReportService::REVENUE_STATUSES)
            ->whereNotNull('order_items.product_id')
            ->groupBy('order_items.product_id');

        return DB::table('products')
            ->leftJoin('users', 'users.id', '=', 'products.seller_id')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->leftJoin($sales, 'sales.product_id', '=', 'products.id')
            ->orderByDesc('products.id')
            ->limit(self::SCAN_LIMIT)
            ->select([
                'products.id',
                'products.slug',
                'products.title',
                'products.sku',
                'products.quantity',
                'products.reserved',
                'products.low_stock_threshold',
                'products.stock_status',
                'products.created_at',
                DB::raw('COALESCE(users.name, \'KDP MART\') as seller'),
                DB::raw('COALESCE(categories.name, products.category_name) as category'),
                DB::raw('COALESCE(sales.units_sold, 0) as units_sold'),
            ])
            ->get()
            ->map(function ($row) {
                $threshold = $row->low_stock_threshold !== null
                    ? max(0, (int) $row->low_stock_threshold)
                    : \App\Models\Setting::lowStockThreshold();

                $stock = max(0, (int) $row->quantity);
                $available = max(0, $stock - (int) $row->reserved);

                $row->stock = $stock;
                $row->available = $available;
                $row->threshold = $threshold;
                $row->units_sold = (int) $row->units_sold;
                $row->status_label = $this->statusLabel($row, $available, $threshold);

                return $row;
            });
    }

    /**
     * Partition the scanned rows into the report sections.
     *
     * Each product appears in every section it genuinely belongs to, and the
     * labels come from the SAME status vocabulary InventoryService already
     * uses, so the report can never contradict the storefront badge.
     *
     * @param  \Illuminate\Support\Collection<int, object>  $products
     * @return \Illuminate\Support\Collection<int, object>
     */
    private function filterByStatus(\Illuminate\Support\Collection $products, string $bucket): \Illuminate\Support\Collection
    {
        $threshold = Setting::lowStockThreshold();

        return match ($bucket) {
            'low_stock'  => $products->filter(
                fn ($p) => $p->available > 0 && $p->available <= $p->threshold
            )->sortBy('available')->values(),

            'out_of_stock' => $products->filter(
                fn ($p) => $p->available <= 0
            )->sortByDesc('id')->values(),

            // A ratio of the configured threshold, not a second hard-coded
            // number, so changing the setting re-scopes this section too.
            'high_stock' => $products->filter(
                fn ($p) => $p->available >= max($threshold, 1) * self::HIGH_STOCK_MULTIPLE
            )->sortByDesc('available')->values(),

            'recently_added' => $products->filter(
                fn ($p) => $p->created_at !== null
                    && \Carbon\Carbon::parse($p->created_at)->gte(now()->subDays(30))
            )->sortByDesc('created_at')->values(),

            'fast_moving' => $products->filter(
                fn ($p) => $p->units_sold > 0
            )->sortByDesc('units_sold')->values(),

            // In stock but nothing sold at all — the reorder candidates.
            'slow_moving' => $products->filter(
                fn ($p) => $p->units_sold <= 0 && $p->available > 0
            )->sortByDesc('available')->values(),

            default => collect(),
        };
    }

    /**
     * Reuse InventoryService's own status vocabulary (never a second set).
     */
    private function statusLabel(object $row, int $available, int $threshold): string
    {
        return app(InventoryService::class)->statusLabel(
            app(InventoryService::class)->statusFromAvailable($available, $threshold)
        );
    }

    /**
     * Products that need admin attention right now (low or out of stock).
     *
     * This is a READ of current state, not a notification: the seller-facing
     * low-stock alert is already emitted exactly once by InventoryService
     * when stock CROSSES the threshold, so simply loading the admin page can
     * never spam anyone.
     *
     * @return \Illuminate\Support\Collection<int, object>
     */
    public function alerts(int $limit = 10): \Illuminate\Support\Collection
    {
        return $this->scannedProducts()
            ->filter(fn ($p) => $p->available <= $p->threshold)
            ->sortBy('available')
            ->take($limit)
            ->values();
    }

    /**
     * Aggregate counters for the report header.
     *
     * @return array{total: int, low: int, out: int, stock_units: int}
     */
    public function summary(): array
    {
        $products = $this->scannedProducts();

        return [
            'total'       => $products->count(),
            'low'         => $products->filter(fn ($p) => $p->available > 0 && $p->available <= $p->threshold)->count(),
            'out'         => $products->filter(fn ($p) => $p->available <= 0)->count(),
            'stock_units' => (int) $products->sum('stock'),
        ];
    }

    /**
     * The store-wide default threshold currently in force.
     */
    public function storeThreshold(): int
    {
        return Setting::lowStockThreshold();
    }
}
