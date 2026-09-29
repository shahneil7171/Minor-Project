<?php

namespace App\Services\Reports;

use App\Models\Order;
use App\Models\ReturnRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * SalesReportService — PHASE 4.
 *
 * Every sales/financial/customer/payment figure on the admin reports is
 * produced here, from DATABASE AGGREGATION (COUNT / SUM / AVG / GROUP BY)
 * inside a single date window.
 *
 * PERFORMANCE RULES
 * -----------------
 * 1. NO FULL-TABLE LOADS. Rows are never pulled into PHP to be summed there;
 *    the database does the work, so a report costs the same on 50 orders as
 *    on 500,000.
 * 2. ONE QUERY PER FIGURE. Each method is self-contained, so a page asks for
 *    exactly the cards it renders.
 * 3. CANCELLED ORDERS NEVER COUNT AS REVENUE, but they DO appear in the
 *    status breakdown (a cancellation is a real order event).
 * 4. NO DOUBLE COUNTING. Units/revenue are summed from `order_items` (the
 *    line level) while order counts come from `orders`, so an order with
 *    three lines is 1 order and 3 units — the correct meaning of each figure.
 */
class SalesReportService
{
    /**
     * Order statuses that represent kept (non-cancelled) business.
     *
     * This is the SINGLE place the "does this order count as revenue" rule
     * lives; the dashboard, the reports and the CSV exports all ask here, so
     * they can never disagree.
     *
     * @var array<int, string>
     */
    public const REVENUE_STATUSES = [
        'confirmed',
        'processing',
        'ready_for_pickup',
        'assigned',
        'picked_up',
        'out_for_delivery',
        'delivered',
    ];

    /**
     * Orders created inside the window (any status).
     */
    private function ordersIn(DateRange $range): Builder
    {
        return Order::query()->whereBetween('created_at', [$range->from, $range->to]);
    }

    /**
     * Orders inside the window that represent kept business.

    // ------------------------------------------------------------------
    // Sales overview
    // ------------------------------------------------------------------

    /**
     * Headline sales figures for the window.
     *
     * @return array{
     *     orders: int, revenue: float, units: int, customers: int, aov: float,
     *     discount: float, tax: float, shipping: float, cancelled: int, refunded: float
     * }
     */
    public function overview(DateRange $range): array
    {
        // Order-level money lives on the order row, so ONE aggregate covers
        // revenue, discounts, tax, shipping and distinct buyers.
        $totals = $this->paidOrdersIn($range)
            ->selectRaw(
                'COUNT(*) as orders,'
                . ' COALESCE(SUM(total), 0) as revenue,'
                . ' COALESCE(SUM(discount_amount), 0) as discount,'
                . ' COALESCE(SUM(tax), 0) as tax,'
                . ' COALESCE(SUM(shipping_cost), 0) as shipping,'
                . ' COUNT(DISTINCT user_id) as customers'
            )
            ->first();

        $orders = (int) ($totals->orders ?? 0);
        $revenue = round((float) ($totals->revenue ?? 0), 2);

        // Units are a LINE-level fact, so they come from order_items.
        $units = (int) DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereIn('orders.status', self::REVENUE_STATUSES)
            ->whereBetween('orders.created_at', [$range->from, $range->to])
            ->sum('order_items.quantity');

        return [
            'orders'    => $orders,
            'revenue'   => $revenue,
            'units'     => $units,
            'customers' => (int) ($totals->customers ?? 0),
            'aov'       => $this->averageOrderValue($revenue, $orders),
            'discount'  => round((float) ($totals->discount ?? 0), 2),
            'tax'       => round((float) ($totals->tax ?? 0), 2),
            'shipping'  => round((float) ($totals->shipping ?? 0), 2),
            'cancelled' => (int) (clone $this->ordersIn($range))->where('status', 'cancelled')->count(),
            'refunded'  => $this->refundedAmount($range),
        ];
    }

    /**
     * Money handed back to buyers (RECORDED refunds, not gateway settlements).
     */
    public function refundedAmount(DateRange $range): float
    {
        return round((float) ReturnRequest::query()
            ->where('refund_status', 'refunded')
            ->whereBetween('refunded_at', [$range->from, $range->to])
            ->sum('refund_amount'), 2);
    }

    /**
     * AOV = revenue / orders, guarded against a zero-order window.
     */
    public function averageOrderValue(float $revenue, int $orders): float
    {
        return $orders > 0 ? round($revenue / $orders, 2) : 0.0;
    }

    /**
     * Average order value across fixed windows (Today / 7d / 30d / month /
     * year) so the reports can show the trend in a single panel.
     *
     * @return array<string, array{label: string, orders: int, revenue: float, aov: float}>
     */
    public function aovAcrossPeriods(): array
    {
        $resolver = app(DateRangeService::class);

        $windows = [
            'today'      => ['Today', 'today'],
            'last_7'     => ['Last 7 Days', 'last_7_days'],
            'last_30'    => ['Last 30 Days', 'last_30_days'],
            'this_month' => ['This Month', 'this_month'],
            'this_year'  => ['This Year', 'this_year'],
        ];

        $out = [];

        foreach ($windows as $key => [$label, $preset]) {
            $range = $resolver->for($preset);

            $row = $this->paidOrdersIn($range)
                ->selectRaw('COUNT(*) as orders, COALESCE(SUM(total), 0) as revenue')
                ->first();

            $orders = (int) ($row->orders ?? 0);
            $revenue = round((float) ($row->revenue ?? 0), 2);

            $out[$key] = [
                'label'   => $label,
                'orders'  => $orders,
                'revenue' => $revenue,
                'aov'     => $this->averageOrderValue($revenue, $orders),
            ];
        }

        return $out;
    }

    // ------------------------------------------------------------------
    // Sales chart
    // ------------------------------------------------------------------

    /**
     * The "Date | Orders | Revenue" series behind the sales chart + table.
     *
     * The bucket is chosen from the window's own length (a single day is
     * bucketed hourly, a month daily, a year monthly) so the chart never
     * renders 900 points nor collapses everything into one bar. Every bucket
     * is pre-created so empty days still render as zero.
     *
     * @return array{
     *     labels: array<int, string>, orders: array<int, int>,
     *     revenue: array<int, float>, rows: array<int, array>
     * }
     */
    public function salesSeries(DateRange $range): array
    {
        [$step, $sqlFormat, $phpFormat] = $this->granularityFor($range);

        // Database-side bucketing via strftime (works on MySQL + SQLite).
        $grouped = $this->ordersIn($range)
            ->selectRaw("strftime('{$sqlFormat}', created_at) as bucket, status, COUNT(*) as orders, COALESCE(SUM(total), 0) as revenue")
            ->groupBy('bucket', 'status')
            ->get()
            ->groupBy('bucket');

        $buckets = [];
        $cursor = $range->from->startOfDay();

        while ($cursor->lessThanOrEqualTo($range->to)) {
            $buckets[$cursor->format($phpFormat)] = ['orders' => 0, 'revenue' => 0.0];
            $cursor = $cursor->add($step);
        }

        foreach ($grouped as $bucket => $statusRows) {
            $key = $this->normaliseBucket((string) $bucket, $phpFormat);

            if (! isset($buckets[$key])) {
                continue;
            }

            foreach ($statusRows as $row) {
                // A cancelled order still COUNTS as an order event but adds
                // no revenue — identical to overview()'s rule.
                $buckets[$key]['orders'] += (int) $row->orders;

                if (in_array($row->status, self::REVENUE_STATUSES, true)) {
                    $buckets[$key]['revenue'] += (float) $row->revenue;
                }
            }
        }

        $labels = $orders = $revenue = $rows = [];

        foreach ($buckets as $key => $bucket) {
            $label = $this->labelFor($key, $range);

            $labels[] = $label;
            $orders[] = $bucket['orders'];
            $revenue[] = round($bucket['revenue'], 2);
            $rows[] = [
                'date'    => $key,
                'label'   => $label,
                'orders'  => $bucket['orders'],
                'revenue' => round($bucket['revenue'], 2),
            ];
        }

        return ['labels' => $labels, 'orders' => $orders, 'revenue' => $revenue, 'rows' => $rows];
    }

    /**
     * Bucket size for the window: [step, sql strftime fmt, php date fmt].
     *
     * @return array{0: string, 1: string, 2: string}
     */
    private function granularityFor(DateRange $range): array
    {
        $days = $range->days();

        if ($days <= 1) {
            return ['hour', '%Y-%m-%d %H:00', 'Y-m-d H:00'];
        }

        if ($days <= 92) {
            return ['day', '%Y-%m-%d', 'Y-m-d'];
        }

        return ['month', '%Y-%m-01', 'Y-m-01'];
    }

    /**
     * Normalise a SQL bucket so its key matches the PHP-side bucket keys.
     */
    private function normaliseBucket(string $bucket, string $phpFormat): string
    {
        $bucket = trim($bucket);

        if (str_ends_with($phpFormat, 'H:00') && preg_match('/^\d{4}-\d{2}-\d{2} \d{2}/', $bucket, $m)) {
            return $m[0];
        }

        if (str_ends_with($phpFormat, '-01') && preg_match('/^\d{4}-\d{2}/', $bucket, $m)) {
            return $m[0] . '-01';
        }

        return $bucket;
    }

    /**
     * Short human label for a bucket key.
     */
    private function labelFor(string $key, DateRange $range): string
    {
        $parsed = null;

        try {
            $parsed = \Carbon\CarbonImmutable::createFromFormat('Y-m-d H:00', $key)
                ?: \Carbon\CarbonImmutable::createFromFormat('Y-m-d', $key);
        } catch (\Throwable) {
            $parsed = null;
        }

        if (! $parsed instanceof \Carbon\CarbonImmutable) {
            return $key;
        }

        if ($range->isMultiDay() && $range->days() > 92) {
            return $parsed->format('M Y');
        }

        return $parsed->format($range->isSingleDay() ? 'g:i A' : 'd M');
    }

    // ------------------------------------------------------------------
    // Order status report
    // ------------------------------------------------------------------

    /**
     * Visual breakdown of the order lifecycle: count + percentage per status.
     *
     * The status list is read from Order::STATUSES / Order::STATUS_LABELS —
     * the SAME constants the workflow itself uses. There is no second,
     * report-only status vocabulary, so a status added to the lifecycle
     * automatically appears here (and can never drift out of sync).
     *
     * @return array<int, array{status: string, label: string, count: int, percent: float}>
     */
    public function orderStatusBreakdown(DateRange $range): array
    {
        $counts = $this->ordersIn($range)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        $total = 0;
        foreach ($counts as $value) {
            $total += (int) $value;
        }

        $rows = [];

        foreach (Order::STATUSES as $status) {
            $count = (int) ($counts[$status] ?? 0);

            $rows[] = [
                'status'  => $status,
                'label'   => Order::STATUS_LABELS[$status] ?? ucfirst(str_replace('_', ' ', $status)),
                'count'   => $count,
                // A zero-order window yields 0%, never a division by zero.
                'percent' => $total > 0 ? round($count * 100 / $total, 1) : 0.0,
            ];
        }

        return $rows;
    }

    // ------------------------------------------------------------------
    // Financial summary
    // ------------------------------------------------------------------

    /**
     * Financial summary built ONLY from columns that actually exist.
     *
     * Every figure maps to a real `orders` column — nothing is estimated and
     * no gateway-settled amount is claimed. "Gross Sales" is the sum of
     * recorded order subtotals; UPI payments in this project are confirmed
     * MANUALLY, so no figure here may be described as bank-verified.
     *
     * @return array<string, array{value: float, note: string}>
     */
    public function financialSummary(DateRange $range): array
    {
        $row = $this->paidOrdersIn($range)
            ->selectRaw(
                'COALESCE(SUM(subtotal), 0) as subtotal,'
                . ' COALESCE(SUM(total), 0) as total,'
                . ' COALESCE(SUM(discount_amount), 0) as discount,'
                . ' COALESCE(SUM(shipping_cost), 0) as shipping,'
                . ' COALESCE(SUM(tax), 0) as tax'
            )
            ->first();

        $gross = round((float) ($row->subtotal ?? 0), 2);
        $discount = round((float) ($row->discount ?? 0), 2);
        $refund = $this->refundedAmount($range);

        // Net = goods revenue after discounts, minus money returned to buyers.
        // Shipping and tax are pass-through, so they are reported separately
        // rather than folded into "net".
        $net = round(max(0.0, $gross - $discount - $refund), 2);

        return [
            'gross_sales' => [
                'value' => $gross,
                'note'  => 'Sum of order subtotals (cancelled orders excluded).',
            ],
            'discounts' => [
                'value' => $discount,
                'note'  => 'Coupon discounts recorded on orders in this period.',
            ],
            'shipping_revenue' => [
                'value' => round((float) ($row->shipping ?? 0), 2),
                'note'  => 'Shipping charged to customers.',
            ],
            'tax_collected' => [
                'value' => round((float) ($row->tax ?? 0), 2),
                'note'  => 'Tax added at checkout. Not remitted to any authority by this app.',
            ],
            'refund_amount' => [
                'value' => $refund,
                'note'  => 'Refunds marked refunded in the returns workflow (recorded, not gateway-settled).',
            ],
            'net_sales' => [
                'value' => $net,
                'note'  => 'Gross sales − discounts − refunds.',
            ],
            'recorded_order_total' => [
                'value' => round((float) ($row->total ?? 0), 2),
                'note'  => 'Sum of orders.total (subtotal − discount + shipping + tax).',
            ],
        ];
    }

    // ------------------------------------------------------------------
    // Payment overview
    // ------------------------------------------------------------------

    /**
     * Orders grouped by the payment method recorded at checkout.
     *
     * HONESTY NOTE — KDP MART has NO payment gateway. `orders.payment_method`
     * stores the method the buyer selected, and a UPI transfer is confirmed
     * MANUALLY by the platform. The three status columns are therefore
     * DERIVED from the order lifecycle (and labelled that way in the UI):
     *
     *   - pending   : order not yet delivered (not collected)
     *   - completed : delivered orders (money the platform recorded)
     *   - failed    : cancelled orders (no payment expected)
     *
     * They are NOT gateway confirmation states and must never be presented
     * as "automatically verified by the bank".
     *
     * @return array<int, array{method: string, orders: int, amount: float, pending: int, completed: int, failed: int}>
     */
    public function paymentOverview(DateRange $range): array
    {
        $grouped = $this->ordersIn($range)
            ->selectRaw('payment_method, status, COUNT(*) as orders, COALESCE(SUM(total), 0) as amount')
            ->groupBy('payment_method', 'status')
            ->get()
            ->groupBy('payment_method');

        // Always list the methods the checkout form offers, plus any legacy
        // value that may already exist in the data.
        $methods = array_values(array_unique(array_filter(
            array_merge(['Cash on Delivery', 'UPI', 'Card'], $grouped->keys()->all()),
            fn ($m) => $m !== null && $m !== '',
        )));

        $out = [];

        foreach ($methods as $method) {
            $entry = [
                'method'    => $method,
                'orders'    => 0,
                'amount'    => 0.0,
                'pending'   => 0,
                'completed' => 0,
                'failed'    => 0,
            ];

            foreach ($grouped->get($method, collect()) as $row) {
                $count = (int) $row->orders;

                $entry['orders'] += $count;
                $entry['amount'] += (float) $row->amount;

                if ($row->status === 'cancelled') {
                    $entry['failed'] += $count;
                } elseif ($row->status === 'delivered') {
                    $entry['completed'] += $count;
                } else {
                    $entry['pending'] += $count;
                }
            }

            $entry['amount'] = round($entry['amount'], 2);
            $out[] = $entry;
        }

        return $out;
    }

    // ------------------------------------------------------------------
    // Customer analytics
    // ------------------------------------------------------------------

    /**
     * Customer metrics for the window.
     *
     * "New" customers registered inside the window; "Active" customers are
     * those who placed at least one non-cancelled order in it; "Returning"
     * customers are repeat buyers (2+ orders ever) — the standard definition.
     * Every figure is derived with COUNT/GROUP BY, never by looping in PHP.
     *
     * @return array{
     *     total_customers: int, new_customers: int, active_customers: int,
     *     returning_customers: int, orders_per_customer: float,
     *     average_order_value: float, total_spend: float
     * }
     */
    public function customerAnalytics(DateRange $range): array
    {
        $totalCustomers = (int) User::query()->where('account_type', 'buyer')->count();

        $newCustomers = (int) User::query()
            ->where('account_type', 'buyer')
            ->whereBetween('created_at', [$range->from, $range->to])
            ->count();

        // Buyers who ordered in the window (a sub-select keeps this one query).
        $activeCustomers = (int) User::query()
            ->where('account_type', 'buyer')
            ->whereIn('id', fn ($q) => $this->paidOrdersIn($range)->select('user_id'))
            ->count();

        // Repeat buyers: 2+ orders EVER.
        $returningCustomers = (int) User::query()
            ->where('account_type', 'buyer')
            ->whereIn('id', function ($q) {
                $q->select('user_id')
                    ->from('orders')
                    ->groupBy('user_id')
                    ->havingRaw('COUNT(*) > 1');
            })
            ->count();

        $overview = $this->overview($range);
        $orders = $overview['orders'];

        return [
            'total_customers'     => $totalCustomers,
            'new_customers'       => $newCustomers,
            'active_customers'    => $activeCustomers,
            'returning_customers' => $returningCustomers,
            // Guarded: no orders / no customers => 0, never a division error.
            'orders_per_customer' => $activeCustomers > 0 ? round($orders / $activeCustomers, 2) : 0.0,
            'average_order_value' => $this->averageOrderValue($overview['revenue'], $orders),
            'total_spend'         => $overview['revenue'],
        ];
    }

    /**
     * Top customers by spend inside the window.
     *
     * Only non-sensitive columns are selected (never password, never
     * remember_token) and the aggregation happens in SQL.
     *
     * @return \Illuminate\Support\Collection<int, object>
     */
    public function topCustomers(DateRange $range, int $limit = 10)
    {
        return DB::table('orders')
            ->join('users', 'users.id', '=', 'orders.user_id')
            ->whereIn('orders.status', self::REVENUE_STATUSES)
            ->whereBetween('orders.created_at', [$range->from, $range->to])
            ->groupBy('orders.user_id', 'users.name', 'users.email')
            ->orderByDesc('total_spend')
            ->limit($limit)
            ->select([
                'orders.user_id as id',
                'users.name',
                'users.email',
                DB::raw('COUNT(*) as orders'),
                DB::raw('COALESCE(SUM(orders.total), 0) as total_spend'),
            ])
            ->get();
    }

    /**
     * Whether a real payment gateway is integrated (it is not).
     *
     * The UI reads this to decide whether it must label payment figures as
     * "manually recorded" instead of "verified".
     */
    public function hasPaymentGateway(): bool
    {
        return false;
    }
}

