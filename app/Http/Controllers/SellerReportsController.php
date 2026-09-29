<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ReturnRequest;
use App\Models\User;
use App\Services\InventoryService;
use App\Services\Reports\DateRange;
use App\Services\Reports\DateRangeService;
use App\Services\Reports\ReportExportService;
use App\Services\Reports\SalesReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Seller > Reports (Phase 4, Part 26).
 *
 * A seller sees ONLY their own commercial data: their sales, orders,
 * products, units sold, revenue, returns, low stock and top products.
 *
 * ISOLATION GUARANTEES
 * --------------------
 * 1. The route is behind `auth` + `seller` middleware.
 * 2. EVERY query is scoped by `seller_id = auth()->id()` (or by
 *    `order_items.seller_id` for order lines) — there is no code path that
 *    can read another seller's rows, so Seller A can never see Seller B's
 *    orders, revenue, customers or products.
 * 3. No seller payment profile data is read at all: the controller never
 *    touches `seller_payment_profiles`, and the export whitelist contains
 *    only commercial columns.
 */
class SellerReportsController extends Controller
{
    /**
     * The seller's own report page.
     */
    public function index(Request $request): View
    {
        $seller = $request->user();
        $range = app(DateRangeService::class)->resolve($request);

        return view('seller.reports', [
            'range'       => $range,
            'presets'     => DateRangeService::PRESETS,
            'overview'    => $this->overview($seller, $range),
            'topProducts' => $this->topProducts($seller, $range),
            'lowStock'    => $this->lowStock($seller),
            'returns'     => $this->returns($seller, $range),
        ]);
    }

    /**
     * Seller sales export — scoped to the signed-in seller's own lines.
     */
    public function export(Request $request): StreamedResponse
    {
        $seller = $request->user();
        $range = app(DateRangeService::class)->resolve($request);

        return app(ReportExportService::class)->exportSalesLines($range, $seller->id);
    }

    /**
     * Headline counters, all scoped to the signed-in seller.
     *
     * @return array<string, mixed>
     */
    private function overview(User $seller, DateRange $range): array
    {
        // Revenue is the seller's SHARE of each order (their line subtotals),
        // so a multi-seller order never inflates one seller's figures.
        $totals = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('order_items.seller_id', $seller->id)
            ->whereIn('orders.status', SalesReportService::REVENUE_STATUSES)
            ->whereBetween('orders.created_at', [$range->from, $range->to])
            ->selectRaw(
                'COUNT(DISTINCT order_items.order_id) as orders,'
                . ' COALESCE(SUM(order_items.quantity), 0) as units,'
                . ' COALESCE(SUM(order_items.subtotal), 0) as revenue'
            )
            ->first();

        $orders = (int) ($totals->orders ?? 0);
        $revenue = round((float) ($totals->revenue ?? 0), 2);

        return [
            'orders'   => $orders,
            'units'    => (int) ($totals->units ?? 0),
            'revenue'  => $revenue,
            'aov'      => $orders > 0 ? round($revenue / $orders, 2) : 0.0,
            'products' => (int) Product::where('seller_id', $seller->id)->count(),
        ];
    }

    /**
     * This seller's best sellers (their own lines only).
     *
     * @return \Illuminate\Support\Collection<int, object>
     */
    private function topProducts(User $seller, DateRange $range)
    {
        return DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('order_items.seller_id', $seller->id)
            ->whereIn('orders.status', SalesReportService::REVENUE_STATUSES)
            ->whereBetween('orders.created_at', [$range->from, $range->to])
            ->groupBy('order_items.product_slug')
            ->orderByDesc('units')
            ->limit(10)
            ->select([
                'order_items.product_slug',
                DB::raw('MAX(order_items.product_title) as product_title'),
                DB::raw('SUM(order_items.quantity) as units'),
                DB::raw('COALESCE(SUM(order_items.subtotal), 0) as revenue'),
            ])
            ->get();
    }

    /**
     * The seller's own low-stock products, using the SAME threshold and
     * availability rules as the storefront (InventoryService).
     *
     * @return \Illuminate\Support\Collection<int, Product>
     */
    private function lowStock(User $seller)
    {
        $inventory = app(InventoryService::class);

        return Product::query()
            ->where('seller_id', $seller->id)
            ->orderBy('id')
            ->get()
            ->map(function (Product $product) use ($inventory) {
                $threshold = $inventory->thresholdFor($product);
                $available = $inventory->availableFor($product);

                $product->available = $available;
                $product->threshold = $threshold;
                $product->report_status = $inventory->statusLabel(
                    $inventory->statusFromAvailable($available, $threshold)
                );

                return $product;
            })
            ->filter(fn (Product $product) => $product->available <= $product->threshold)
            ->values();
    }

    /**
     * Returns raised against the seller's own products.
     *
     * @return array<string, int>
     */
    private function returns(User $seller, DateRange $range): array
    {
        $base = ReturnRequest::query()
            ->forSeller($seller->id)
            ->whereBetween('created_at', [$range->from, $range->to]);

        return [
            'total'    => (int) (clone $base)->count(),
            'pending'  => (int) (clone $base)->where('status', 'pending')->count(),
            'approved' => (int) (clone $base)->whereIn('status', ['approved', 'pickup_assigned', 'picked_up', 'received', 'refund_processing', 'completed'])->count(),
            'rejected' => (int) (clone $base)->whereIn('status', ['rejected', 'cancelled'])->count(),
            'refunded' => (int) (clone $base)->where('refund_status', 'refunded')->count(),
        ];
    }
}

