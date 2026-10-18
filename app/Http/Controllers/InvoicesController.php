<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\User;
use App\Services\InvoicePdfService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * PHASE 6 — PDF invoices & receipts with proper access control.
 *
 * Three audiences, each re-authorized SERVER-SIDE (a hidden link is never
 * the protection):
 *   - Customer: own orders only (admins may also open any customer's).
 *   - Admin:    any order (route also behind the `admin` middleware).
 *   - Seller:   orders containing at least one of their own lines; the
 *               document itself is scoped to those lines only.
 */
class InvoicesController extends Controller
{
    public function __construct(private InvoicePdfService $invoices) {}

    /**
     * Customer invoice PDF — GET /orders/{order}/invoice
     */
    public function customerInvoice(Request $request, Order $order)
    {
        $this->authorizeOrderViewer($request, $order);

        return $this->download($order, 'invoice');
    }

    /**
     * Customer payment receipt PDF — GET /orders/{order}/receipt
     */
    public function customerReceipt(Request $request, Order $order)
    {
        $this->authorizeOrderViewer($request, $order);

        return $this->download($order, 'receipt');
    }

    /**
     * Admin invoice PDF — GET /admin/orders/{order}/invoice/pdf
     */
    public function adminInvoice(Request $request, Order $order)
    {
        abort_unless($request->user()?->isAdmin(), 403);

        return $this->download($order, 'invoice');
    }

    /**
     * Seller invoice PDF (own lines only) — GET /seller/orders/{order}/invoice
     */
    public function sellerInvoice(Request $request, Order $order)
    {
        $seller = $request->user();

        // The seller must own at least one line of this order — the exact
        // same rule the seller order pages enforce.
        abort_unless(
            $order->items()->where('seller_id', $seller->id)->exists(),
            403,
            'You can only download invoices that contain your products.'
        );

        return $this->download($order, 'invoice', $seller);
    }

    /**
     * A user may only download paperwork for their own orders; admins may
     * open any customer's (mirrors OrdersController::show).
     */
    private function authorizeOrderViewer(Request $request, Order $order): void
    {
        $user = $request->user();

        if ($order->user_id !== $user->id && ! $user->isAdmin()) {
            abort(403);
        }
    }

    /**
     * Stream the PDF with a deterministic, order-unique filename.
     */
    private function download(Order $order, string $type, ?User $scopedSeller = null): Response
    {
        $pdf = $this->invoices->render($order, $type, $scopedSeller);

        // order_number is unique per order, so invoice numbers never collide
        // and re-downloading the same document is always deterministic.
        $safeNumber = preg_replace('/[^A-Za-z0-9_-]/', '', (string) $order->order_number);
        $filename = ($type === 'receipt' ? 'receipt-' : 'invoice-') . $safeNumber . '.pdf';

        return response($pdf, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
}