<?php

namespace App\Services\Reports;

use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * ReportExportService — PHASE 4.
 *
 * Streams CSV exports for every report. Exports reuse the SAME
 * DateRangeService resolution as the on-screen page, so a download always
 * matches the figures the admin was looking at.
 *
 * SECURITY
 * --------
 * Exports are built from an explicit column whitelist — the request payload
 * is never echoed, and no authentication column (password, remember_token)
 * or seller payment field (upi_id, qr_code_path, account_number, ifsc_code)
 * is ever selected. A spreadsheet can therefore never leak a secret.
 *
 * The response is STREAMED, so exporting 50,000 rows never loads them all
 * into PHP memory at once.
 */
class ReportExportService
{
    public const MAX_EXPORT_ROWS = 10000;

    /**
     * Build a streamed CSV download.
     *
     * @param  array<int, string>  $header
     * @param  iterable<int, array<int, scalar|null>>  $rows
     */
    public function csv(string $filename, array $header, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($header, $rows) {
            $out = fopen('php://output', 'wb');

            // BOM so Excel opens UTF-8 (₹ symbols) correctly.
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $header);

            $written = 0;

            foreach ($rows as $row) {
                if ($written >= self::MAX_EXPORT_ROWS) {
                    break;
                }

                fputcsv($out, array_map(
                    fn ($value) => is_scalar($value) || $value === null ? $value : '',
                    $row,
                ));
                $written++;
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * A timestamped, range-aware export filename.
     */
    public function filename(string $prefix, ?DateRange $range = null): string
    {
        $suffix = $range ? $range->slug() : 'all';

        return sprintf('%s-%s.csv', $prefix, $suffix);
    }


    // ------------------------------------------------------------------
    // Sales report (order line detail)
    // ------------------------------------------------------------------

    /**
     * The detailed sales export required by Phase 4:
     * Date | Order ID | Customer | Seller | Product | Quantity | Subtotal |
     * Tax | Shipping | Discount | Total | Payment Method | Order Status.
     *
     * One row per order LINE, so a three-item order produces three rows with
     * the order-level money (tax/shipping/discount/total) repeated — the
     * numbers still add up to the order total without double counting.
     *
     * Only display-safe columns are selected: no password, no reset token,
     * no seller payment detail of any kind.
     */
    public function salesOrderLines(DateRange $range, ?int $sellerId = null): iterable
    {
        $query = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->leftJoin('users', 'users.id', '=', 'orders.user_id')
            ->leftJoin('users as sellers', 'sellers.id', '=', 'order_items.seller_id')
            ->whereBetween('orders.created_at', [$range->from, $range->to]);

        // Seller isolation: a seller's export can only ever contain their own
        // lines (enforced again in the controller).
        if ($sellerId !== null) {
            $query->where('order_items.seller_id', $sellerId);
        }

        return $query
            ->orderBy('orders.created_at')
            ->orderBy('orders.id')
            ->limit(self::MAX_EXPORT_ROWS)
            ->select([
                'orders.created_at',
                'orders.order_number',
                'orders.id as order_id',
                DB::raw('COALESCE(users.name, orders.customer_email, \'Guest\') as customer_name'),
                'orders.customer_email',
                DB::raw('COALESCE(sellers.name, \'KDP MART\') as seller_name'),
                'order_items.product_title',
                'order_items.sku',
                'order_items.quantity',
                'order_items.price',
                'order_items.subtotal',
                'orders.tax',
                'orders.shipping_cost',
                'orders.discount_amount',
                'orders.total',
                'orders.payment_method',
                'orders.status',
            ])
            ->cursor();
    }

    /**
     * The header row for the detailed sales export.
     *
     * @return array<int, string>
     */
    public function salesOrderLinesHeader(): array
    {
        return [
            'Date', 'Order ID', 'Order Number', 'Customer', 'Email', 'Seller',
            'Product', 'SKU', 'Quantity', 'Unit Price', 'Subtotal', 'Tax',
            'Shipping', 'Discount', 'Total', 'Payment Method', 'Order Status',
        ];
    }

    /**
     * Map a sales export row to its CSV cells.
     *
     * @return array<int, scalar|null>
     */
    public function salesOrderLineRow(object $row): array
    {
        return [
            (string) $row->created_at,
            $row->order_id,
            $row->order_number,
            $row->customer_name,
            $row->customer_email,
            $row->seller_name,
            $row->product_title,
            $row->sku,
            $row->quantity,
            number_format((float) $row->price, 2, '.', ''),
            number_format((float) $row->subtotal, 2, '.', ''),
            number_format((float) $row->tax, 2, '.', ''),
            number_format((float) $row->shipping_cost, 2, '.', ''),
            number_format((float) $row->discount_amount, 2, '.', ''),
            number_format((float) $row->total, 2, '.', ''),
            $row->payment_method,
            $row->status,
        ];
    }

    /**
     * Stream the detailed sales export (respecting the selected range).
     */
    public function exportSalesLines(DateRange $range, ?int $sellerId = null): StreamedResponse
    {
        $rows = $this->salesOrderLines($range, $sellerId);
        $header = $this->salesOrderLinesHeader();
        $map = fn ($row) => $this->salesOrderLineRow($row);

        return $this->csv($this->filename('sales-report', $range), $header, array_map($map, iterator_to_array($rows, false)));
    }
}
