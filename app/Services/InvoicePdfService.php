<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\View;

/**
 * PHASE 6 — invoice & receipt PDF rendering.
 *
 * One service renders BOTH documents from a single dompdf-safe Blade view:
 *   - type "invoice" — the full billing document (items + totals + payment)
 *   - type "receipt" — the same document with a prominent payment-state
 *     banner (PAID / PAYMENT PENDING / PAYABLE ON DELIVERY / FAILED)
 *
 * SELLER SCOPE — passing a seller renders ONLY that seller's order lines and
 * their combined total; buyer payment references, refunds and order-level
 * charges (coupon / shipping / tax / Smart Coins) are never included, so a
 * seller document cannot leak another seller's economics.
 *
 * Money always renders through Order::money() (the currency code + rate the
 * order snapshotted at checkout), so a later exchange-rate edit can never
 * rewrite a historical invoice.
 */
class InvoicePdfService
{
    /**
     * Render the document to a PDF binary string.
     *
     * dompdf is configured WITHOUT remote fetching (no server-side requests
     * triggered by document content) and with DejaVu Sans as the default
     * font so the ₹ currency glyph embeds correctly.
     */
    public function render(Order $order, string $type = 'invoice', ?User $scopedSeller = null): string
    {
        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($this->html($order, $type, $scopedSeller));
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    /**
     * Render the document HTML (the single source the PDF is built from).
     *
     * Exposed publicly so tests — and any future HTML preview — can assert
     * on the exact content without parsing a compressed PDF stream.
     */
    public function html(Order $order, string $type = 'invoice', ?User $scopedSeller = null): string
    {
        $order->loadMissing(['user', 'items']);

        $items = $scopedSeller
            ? $order->items->where('seller_id', $scopedSeller->id)->values()
            : $order->items;

        // Payment details, refunds and order-level totals are buyer/admin
        // information only — a seller copy lists the seller's own lines.
        $payment = $scopedSeller ? null : $this->paymentSummary($order);
        $refunds = $scopedSeller
            ? collect()
            : $order->refunds()->where('status', 'completed')->get();

        return View::make('invoices.pdf', [
            'order'         => $order,
            'type'          => $type === 'receipt' ? 'receipt' : 'invoice',
            'title'         => $type === 'receipt' ? 'Payment Receipt' : 'Invoice',
            'items'         => $items,
            'scopedSeller'  => $scopedSeller,
            'payment'       => $payment,
            'refunds'       => $refunds,
            'grandPaid'     => $payment !== null && $payment['state'] === 'paid',
            'store'         => [
                'name'  => \App\Models\Setting::get('store_name') ?: 'KDP MART',
                'email' => \App\Models\Setting::get('store_email') ?: 'support@kdpmart.test',
                'phone' => \App\Models\Setting::get('store_phone') ?: '',
            ],
            'generatedAt'   => now(),
        ])->render();
    }

    /**
     * The buyer-facing payment summary for the document banner + details.
     *
     * COD / legacy rows (payment_provider null) are never "captured" online:
     * they read as payable-until-delivered, then paid-on-delivery.
     *
     * @return array{state: string, banner: string, label: string, method: string, reference: ?string}
     */
    public function paymentSummary(Order $order): array
    {
        $method = $order->payment_method ?: 'Cash on Delivery';

        if ($order->isGateway()) {
            // Reference = the provider payment id, MASKED (last 8 chars) —
            // the full id is never rendered into a downloadable document.
            $reference = $order->isRazorpay()
                ? $order->maskedRazorpayPaymentId()
                : $order->maskedCashfreePaymentId();

            if ($order->isPaymentCaptured()) {
                return [
                    'state'     => 'paid',
                    'banner'    => 'PAID',
                    'label'     => 'Payment captured',
                    'method'    => $method,
                    'reference' => $reference,
                ];
            }

            if ($order->isPaymentFailed()) {
                return [
                    'state'     => 'failed',
                    'banner'    => 'PAYMENT FAILED',
                    'label'     => 'Payment failed',
                    'method'    => $method,
                    'reference' => $reference,
                ];
            }

            return [
                'state'     => 'pending',
                'banner'    => 'PAYMENT PENDING',
                'label'     => 'Payment pending',
                'method'    => $method,
                'reference' => $reference,
            ];
        }

        // COD / manual methods: the money changes hands on delivery.
        if ($order->isDelivered()) {
            return [
                'state'     => 'paid',
                'banner'    => 'PAID — CASH ON DELIVERY',
                'label'     => 'Paid on delivery',
                'method'    => $method,
                'reference' => null,
            ];
        }

        return [
            'state'     => 'due',
            'banner'    => 'PAYABLE ON DELIVERY',
            'label'     => 'Amount payable on delivery',
            'method'    => $method,
            'reference' => null,
        ];
    }
}