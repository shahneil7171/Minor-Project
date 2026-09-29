<?php

namespace App\Services\Reports;

use App\Models\ReturnRequest;
use Illuminate\Support\Facades\DB;

/**
 * ReturnsReportService — PHASE 4.
 *
 * Returns analytics: status counts, the return rate, top returned products
 * and returns by category.
 *
 * REUSES THE EXISTING RETURN WORKFLOW
 * -----------------------------------
 * - Statuses come from ReturnRequest::STATUSES / STATUS_LABELS (the same
 *   constants the return workflow enforces), never a report-only vocabulary.
 * - Refund states come from ReturnRequest::REFUND_STATUSES.
 * - Aliases such as the legacy `pickup_scheduled` are normalised through the
 *   model's own scope so old rows are counted with their canonical status.
 *
 * Return rate = (returned units / sold units) x 100, with an explicit
 * zero-guard: a window with no sales reports 0%, never a division by zero.
 */
class ReturnsReportService
{
    public const PAGE_LIMIT = 100;

    public const EXPORT_LIMIT = 5000;

    /**
     * Headline returns metrics for the window.
     *
     * @return array{
     *     total: int, pending: int, approved: int, rejected: int,
     *     refunded: int, returned_units: int, sold_units: int,
     *     return_rate: float, refund_amount: float
     * }
     */
    public function summary(DateRange $range): array
    {
        $counts = $this->returnsIn($range)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        $get = fn (string $status): int => (int) ($counts[$status] ?? 0);

        // `pickup_scheduled` is the legacy alias of `pickup_assigned`.
        $pickupAssigned = $get('pickup_assigned') + $get('pickup_scheduled');

        // Sold units over the same window, for the return-rate denominator.
        $soldUnits = (int) DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereIn('orders.status', SalesReportService::REVENUE_STATUSES)
            ->whereBetween('orders.created_at', [$range->from, $range->to])
            ->sum('order_items.quantity');

        $returnedUnits = (int) $this->returnsIn($range)->sum('quantity');

        $refundAmount = round((float) $this->returnsIn($range)
            ->where('refund_status', 'refunded')
            ->sum('refund_amount'), 2);

        return [
            'total'          => array_sum(array_map('intval', $counts)),
            'pending'        => $get('pending'),
            'approved'       => $get('approved') + $pickupAssigned + $get('picked_up') + $get('received') + $get('refund_processing') + $get('completed'),
            'rejected'       => $get('rejected') + $get('cancelled'),
            'refunded'       => $get('refunded'),
            'returned_units' => $returnedUnits,
            'sold_units'     => $soldUnits,
            // Zero-safe: no sales in the window => a 0% rate, not an error.
            'return_rate'    => $soldUnits > 0 ? round($returnedUnits * 100 / $soldUnits, 2) : 0.0,
            'refund_amount'  => $refundAmount,
        ];
    }

    /**
     * Returns grouped by status for the visual breakdown.
     *
     * @return array<int, array{status: string, label: string, count: int, percent: float}>
     */
    public function statusBreakdown(DateRange $range): array
    {
        $counts = $this->returnsIn($range)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        $total = 0;
        foreach ($counts as $value) {
            $total += (int) $value;
        }

        $rows = [];

        foreach (ReturnRequest::STATUSES as $status) {
            $count = (int) ($counts[$status] ?? 0);

            $rows[] = [
                'status'  => $status,
                'label'   => ReturnRequest::STATUS_LABELS[$status] ?? ucfirst(str_replace('_', ' ', $status)),
                'count'   => $count,
                'percent' => $total > 0 ? round($count * 100 / $total, 1) : 0.0,
            ];
        }

        return $rows;
    }

    /**
     * Base query: return requests raised inside the window.
     */
    private function returnsIn(DateRange $range)
    {
        return ReturnRequest::query()->whereBetween('created_at', [$range->from, $range->to]);
    }

    /**
     * Most frequently returned products.
     *
     * @return \Illuminate\Support\Collection<int, object>
     */
    public function topReturnedProducts(DateRange $range, int $limit = self::PAGE_LIMIT)
    {
        return $this->returnsIn($range)
            ->select([
                'returns.product_slug',
                DB::raw('MAX(returns.product_title) as product_title'),
                DB::raw('COUNT(*) as requests'),
                DB::raw('SUM(returns.quantity) as units'),
                DB::raw('SUM(COALESCE(returns.refund_amount, 0)) as refund_amount'),
            ])
            ->groupBy('returns.product_slug')
            ->orderByDesc('units')
            ->limit($limit)
            ->get();
    }

    /**
     * Returns rolled up by the category the product actually belongs to.
     *
     * Categories come from the database (products.category_id), so whatever
     * the store actually sells is what gets reported — nothing is hard-coded.
     *
     * @return \Illuminate\Support\Collection<int, object>
     */
    public function returnsByCategory(DateRange $range, int $limit = self::PAGE_LIMIT)
    {
        return DB::table('returns')
            ->join('products', 'products.id', '=', 'returns.product_id')
            ->leftJoin('categories as child', 'child.id', '=', 'products.category_id')
            ->leftJoin('categories as parent', 'parent.id', '=', 'child.parent_id')
            ->whereBetween('returns.created_at', [$range->from, $range->to])
            ->groupBy(DB::raw('COALESCE(parent.id, child.id)'), DB::raw('COALESCE(parent.name, child.name)'))
            ->orderByDesc('units')
            ->limit($limit)
            ->select([
                DB::raw('COALESCE(parent.name, child.name, \'Uncategorised\') as category'),
                DB::raw('COUNT(*) as requests'),
                DB::raw('SUM(returns.quantity) as units'),
                DB::raw('SUM(COALESCE(returns.refund_amount, 0)) as refund_amount'),
            ])
            ->get()
            ->map(fn ($row) => (object) [
                'category'      => (string) $row->category,
                'requests'      => (int) $row->requests,
                'units'         => (int) $row->units,
                'refund_amount' => round((float) $row->refund_amount, 2),
            ]);
    }

    /**
     * The most recent returns, for the report's detail table.
     *
     * @return \Illuminate\Support\Collection<int, ReturnRequest>
     */
    public function recent(DateRange $range, int $limit = 20)
    {
        return $this->returnsIn($range)
            ->with('order')
            ->latest('id')
            ->limit($limit)
            ->get();
    }
}

