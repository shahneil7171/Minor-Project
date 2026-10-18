<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Refund;
use App\Models\User;
use App\Services\InvoicePdfService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PHASE 6 — PDF invoices & receipts.
 *
 * Covers: access control (IDOR) for customer/admin/seller, PDF binary
 * output, document math (line totals, discounts, shipping, tax, Smart
 * Coins), payment-state banners (COD / gateway / captured / failed),
 * refunds, missing fields, deterministic invoice numbers, and the seller
 * scope guarantee (a seller document never contains another seller's lines
 * or buyer payment internals).
 */
class InvoicePdfTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['account_type' => 'admin']);
    }

    private function buyer(): User
    {
        return User::factory()->create(['account_type' => 'buyer']);
    }

    private function seller(): User
    {
        return User::factory()->create(['account_type' => 'seller']);
    }

    private function makeOrder(User $user, array $overrides = []): Order
    {
        return Order::create(array_merge([
            'user_id'          => $user->id,
            'customer_email'   => $user->email,
            'order_number'     => 'KDP-' . strtoupper(uniqid()),
            'status'           => 'pending',
            'subtotal'         => 100.00,
            'discount_amount'  => 0,
            'coupon_code'      => null,
            'tax'              => 0,
            'shipping_cost'    => 0,
            'total'            => 100.00,
            'coins_redeemed'   => 0,
            'coins_refunded'   => 0,
            'payment_method'   => 'cod',
            'shipping_name'    => $user->name,
            'shipping_phone'   => '9999999999',
            'shipping_address' => '1 Main St',
            'shipping_city'    => 'Springfield',
            'shipping_state'   => 'IL',
            'shipping_pincode' => '10001',
        ], $overrides));
    }

    private function addItem(Order $order, ?User $seller, float $price, int $qty, array $overrides = []): OrderItem
    {
        return $order->items()->create(array_merge([
            'product_slug'  => 'item-' . uniqid(),
            'product_title' => 'Test Item',
            'price'         => $price,
            'quantity'      => $qty,
            'subtotal'      => $price * $qty,
            'seller_id'     => $seller?->id,
        ], $overrides));
    }

    private function assertPdf($response, string $filenamePart): void
    {
        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $response->assertHeader('content-disposition');
        $this->assertStringContainsString(
            $filenamePart,
            (string) $response->headers->get('content-disposition')
        );
        // A real PDF always starts with the %PDF magic bytes.
        $this->assertStringStartsWith('%PDF', (string) $response->getContent());
    }

    /* ------------------------------------------------------------------ */
    /* Access control (IDOR)                                              */
    /* ------------------------------------------------------------------ */

    public function test_guests_are_redirected_from_invoice_and_receipt(): void
    {
        $order = $this->makeOrder($this->buyer());

        $this->get('/orders/' . $order->id . '/invoice')->assertRedirect('/login');
        $this->get('/orders/' . $order->id . '/receipt')->assertRedirect('/login');
    }

    public function test_buyer_can_download_own_invoice_and_receipt_pdfs(): void
    {
        $buyer = $this->buyer();
        $order = $this->makeOrder($buyer);
        $this->addItem($order, null, 50.00, 2);

        $this->assertPdf(
            $this->actingAs($buyer)->get('/orders/' . $order->id . '/invoice'),
            'invoice-' . $order->order_number . '.pdf'
        );
        $this->assertPdf(
            $this->actingAs($buyer)->get('/orders/' . $order->id . '/receipt'),
            'receipt-' . $order->order_number . '.pdf'
        );
    }

    public function test_buyer_cannot_download_another_users_invoice_or_receipt(): void
    {
        $buyer = $this->buyer();
        $other = $this->buyer();
        $order = $this->makeOrder($other);

        $this->actingAs($buyer)->get('/orders/' . $order->id . '/invoice')->assertStatus(403);
        $this->actingAs($buyer)->get('/orders/' . $order->id . '/receipt')->assertStatus(403);
    }

    public function test_admin_can_download_any_orders_invoice_pdf(): void
    {
        $order = $this->makeOrder($this->buyer());

        $this->assertPdf(
            $this->actingAs($this->admin())->get('/admin/orders/' . $order->id . '/invoice/pdf'),
            'invoice-' . $order->order_number . '.pdf'
        );
    }

    public function test_admin_invoice_pdf_route_requires_admin(): void
    {
        $order = $this->makeOrder($this->buyer());

        $this->get('/admin/orders/' . $order->id . '/invoice/pdf')->assertRedirect('/login');
        $this->actingAs($this->buyer())->get('/admin/orders/' . $order->id . '/invoice/pdf')->assertStatus(403);
        $this->actingAs($this->seller())->get('/admin/orders/' . $order->id . '/invoice/pdf')->assertStatus(403);
    }

    /* ------------------------------------------------------------------ */
    /* Seller scope                                                       */
    /* ------------------------------------------------------------------ */

    public function test_seller_can_download_invoice_for_order_containing_their_products(): void
    {
        $seller = $this->seller();
        $order = $this->makeOrder($this->buyer());
        $this->addItem($order, $seller, 250.00, 2, ['product_title' => 'Seller Widget']);

        $this->assertPdf(
            $this->actingAs($seller)->get('/seller/orders/' . $order->id . '/invoice'),
            'invoice-' . $order->order_number . '.pdf'
        );
    }

    public function test_seller_cannot_download_invoice_for_order_without_their_products(): void
    {
        $seller = $this->seller();
        $other = $this->seller();
        $order = $this->makeOrder($this->buyer());
        $this->addItem($order, $other, 100.00, 1);

        $this->actingAs($seller)->get('/seller/orders/' . $order->id . '/invoice')->assertStatus(403);
    }

    public function test_seller_invoice_contains_only_their_own_lines_and_total(): void
    {
        $sellerA = $this->seller();
        $sellerB = $this->seller();
        $buyer = $this->buyer();

        $order = $this->makeOrder($buyer, [
            'subtotal' => 350.00, 'total' => 350.00,
        ]);
        $this->addItem($order, $sellerA, 150.00, 1, ['product_title' => 'Alpha Product']);
        $this->addItem($order, $sellerB, 200.00, 1, ['product_title' => 'Beta Product']);

        $html = app(InvoicePdfService::class)->html($order, 'invoice', $sellerA);

        // Only seller A's line is present.
        $this->assertStringContainsString('Alpha Product', $html);
        $this->assertStringNotContainsString('Beta Product', $html);
        // The scoped total is A's line only (150 x 1), NOT the order subtotal.
        $this->assertStringContainsString('Total for your products', $html);
        $this->assertStringContainsString('₹150.00', $html);
        $this->assertStringNotContainsString('₹350.00', $html);
        // Buyer payment internals never appear on a seller document.
        $this->assertStringNotContainsString('Payment method', $html);
        $this->assertStringNotContainsString('Payment status', $html);
        // (Billed to / Ship to panels DO render — the seller order page already
        // shows customer delivery info, so this matches existing access.)
    }

    /* ------------------------------------------------------------------ */
    /* Document math                                                      */
    /* ------------------------------------------------------------------ */

    public function test_invoice_line_totals_discount_shipping_tax_and_coins_are_correct(): void
    {
        $buyer = $this->buyer();
        // subtotal 1000 + tax 180 + shipping 50 - coupon 100 = 1130 original;
        // minus 200 Smart Coins => amount due 930.
        $order = $this->makeOrder($buyer, [
            'subtotal' => 1000.00, 'tax' => 180.00, 'shipping_cost' => 50.00,
            'discount_amount' => 100.00, 'coupon_code' => 'SAVE100',
            'coins_redeemed' => 200.00, 'total' => 930.00, 'status' => 'delivered',
        ]);
        $this->addItem($order, null, 500.00, 2, ['product_title' => 'Big Ticket Item']);

        $html = app(InvoicePdfService::class)->html($order);

        $this->assertStringContainsString('Big Ticket Item', $html);
        // Line total: 500 x 2 = ₹1,000.00 (appears as unit + line amounts).
        $this->assertStringContainsString('₹500.00', $html);
        $this->assertStringContainsString('₹1,000.00', $html);
        // Every totals row.
        $this->assertStringContainsString('Coupon discount (SAVE100)', $html);
        $this->assertStringContainsString('-₹100.00', $html);
        $this->assertStringContainsString('₹180.00', $html); // tax
        $this->assertStringContainsString('₹50.00', $html);  // shipping
        $this->assertStringContainsString('Original total', $html);
        $this->assertStringContainsString('₹1,130.00', $html); // originalTotal()
        $this->assertStringContainsString('Smart Coins redeemed (200 coins)', $html);
        $this->assertStringContainsString('-₹200.00', $html); // coinsValue()
        // Grand total: amountDue = 930.
        $this->assertStringContainsString('Amount paid', $html);
        $this->assertStringContainsString('₹930.00', $html);
    }

    /* ------------------------------------------------------------------ */
    /* Payment states (receipt banners)                                   */
    /* ------------------------------------------------------------------ */

    public function test_receipt_shows_paid_banner_for_captured_gateway_payment(): void
    {
        $buyer = $this->buyer();
        $order = $this->makeOrder($buyer, [
            'payment_method' => 'Online Payment', 'payment_provider' => 'razorpay',
            'payment_status' => 'captured', 'razorpay_payment_id' => 'rzp_pay_123456789012',
            'total' => 100.00,
        ]);

        $html = app(InvoicePdfService::class)->html($order, 'receipt');

        $this->assertStringContainsString('PAID', $html);
        $this->assertStringContainsString('Payment captured', $html);
        $this->assertStringContainsString('Amount paid', $html);
        // The reference is masked: last 8 chars only, never the full id.
        $this->assertStringContainsString('56789012', $html);
        $this->assertStringNotContainsString('rzp_pay_123456789012', $html);
    }

    public function test_receipt_shows_pending_banner_for_uncaptured_gateway_payment(): void
    {
        $buyer = $this->buyer();
        $order = $this->makeOrder($buyer, [
            'payment_method' => 'Online Payment', 'payment_provider' => 'cashfree',
            'payment_status' => 'pending', 'total' => 100.00,
        ]);

        $html = app(InvoicePdfService::class)->html($order, 'receipt');

        $this->assertStringContainsString('PAYMENT PENDING', $html);
        $this->assertStringContainsString('Amount due', $html);
    }

    public function test_receipt_shows_failed_banner_for_failed_gateway_payment(): void
    {
        $buyer = $this->buyer();
        $order = $this->makeOrder($buyer, [
            'payment_method' => 'Online Payment', 'payment_provider' => 'razorpay',
            'payment_status' => 'failed', 'total' => 100.00,
        ]);

        $this->assertStringContainsString(
            'PAYMENT FAILED',
            app(InvoicePdfService::class)->html($order, 'receipt')
        );
    }

    public function test_cod_receipt_shows_due_banner_until_delivered_then_paid(): void
    {
        $buyer = $this->buyer();
        $pending = $this->makeOrder($buyer, ['payment_method' => 'Cash on Delivery']);

        $pendingHtml = app(InvoicePdfService::class)->html($pending, 'receipt');
        $this->assertStringContainsString('PAYABLE ON DELIVERY', $pendingHtml);
        $this->assertStringContainsString('Amount due', $pendingHtml);

        $delivered = $this->makeOrder($buyer, [
            'payment_method' => 'Cash on Delivery', 'status' => 'delivered',
        ]);

        $deliveredHtml = app(InvoicePdfService::class)->html($delivered, 'receipt');
        $this->assertStringContainsString('PAID — CASH ON DELIVERY', $deliveredHtml);
        $this->assertStringContainsString('Amount paid', $deliveredHtml);
    }

    public function test_invoice_renders_for_cod_order_without_any_gateway_fields(): void
    {
        // Legacy COD rows: payment_provider / payment_status both null.
        $buyer = $this->buyer();
        $order = $this->makeOrder($buyer, ['payment_method' => null]);

        $response = $this->actingAs($buyer)->get('/orders/' . $order->id . '/invoice');

        $this->assertPdf($response, 'invoice-' . $order->order_number . '.pdf');
    }

    /* ------------------------------------------------------------------ */
    /* Refunds, missing fields, determinism                               */
    /* ------------------------------------------------------------------ */

    public function test_completed_refunds_are_listed_on_the_invoice(): void
    {
        $buyer = $this->buyer();
        $order = $this->makeOrder($buyer, ['status' => 'delivered']);
        $item = $this->addItem($order, null, 40.00, 1);

        // refunds.return_request_id is schema-required NOT NULL — a refund is
        // always settled through a return request.
        $return = \App\Models\ReturnRequest::create([
            'order_id' => $order->id, 'order_item_id' => $item->id,
            'user_id' => $buyer->id, 'order_number' => $order->order_number,
            'customer_email' => $buyer->email, 'product_slug' => $item->product_slug,
            'seller_id' => null, 'product_title' => $item->product_title,
            'quantity' => 1, 'reason' => 'Product damaged',
            'status' => 'received', 'refund_status' => 'refunded',
            'refund_amount' => 40.00, 'return_deadline' => now()->addDays(7),
            'requested_at' => now(),
        ]);

        $order->refunds()->create([
            'return_request_id' => $return->id, 'user_id' => $buyer->id,
            'order_item_id' => $item->id, 'amount' => 40.00,
            'method' => 'smart_coins', 'status' => 'completed',
            'idempotency_key' => 'test-refund-' . $return->id,
            'completed_at' => now(),
        ]);
        // A pending refund must NOT appear (money not actually returned yet).
        $pendingReturn = \App\Models\ReturnRequest::create([
            'order_id' => $order->id, 'order_item_id' => $item->id,
            'user_id' => $buyer->id, 'order_number' => $order->order_number,
            'customer_email' => $buyer->email, 'product_slug' => $item->product_slug,
            'seller_id' => null, 'product_title' => $item->product_title,
            'quantity' => 1, 'reason' => 'Wrong size',
            'status' => 'received', 'refund_status' => 'pending',
            'refund_amount' => 99.99, 'return_deadline' => now()->addDays(7),
            'requested_at' => now(),
        ]);
        $order->refunds()->create([
            'return_request_id' => $pendingReturn->id, 'user_id' => $buyer->id,
            'order_item_id' => $item->id, 'amount' => 99.99,
            'method' => 'cod_cash', 'status' => 'pending',
            'idempotency_key' => 'test-refund-pending-' . $pendingReturn->id,
        ]);

        $html = app(InvoicePdfService::class)->html($order);

        $this->assertStringContainsString('Refunds', $html);
        $this->assertStringContainsString('₹40.00', $html);
        $this->assertStringContainsString('Smart Coins', $html); // method label
        $this->assertStringNotContainsString('₹99.99', $html);
    }

    public function test_invoice_renders_when_optional_fields_are_missing(): void
    {
        $buyer = $this->buyer();
        // Minimal order: no coupon, no coins, no items, sparse optional data
        // (shipping_name / phone / address are schema-required NOT NULL).
        $order = Order::create([
            'user_id' => $buyer->id, 'customer_email' => $buyer->email,
            'order_number' => 'KDP-SPARSE-1', 'status' => 'pending',
            'subtotal' => 0, 'tax' => 0, 'shipping_cost' => 0, 'total' => 0,
            'payment_method' => 'cod',
            'shipping_name' => $buyer->name, 'shipping_phone' => '0000000000',
            'shipping_address' => '—', 'shipping_city' => '—',
            'shipping_state' => '—', 'shipping_pincode' => '0',
        ]);

        $response = $this->actingAs($buyer)->get('/orders/' . $order->id . '/invoice');

        $this->assertPdf($response, 'invoice-KDP-SPARSE-1.pdf');

        $html = app(InvoicePdfService::class)->html($order);
        $this->assertStringContainsString('No items recorded for this order.', $html);
    }

    public function test_invoice_number_is_deterministic_and_unique_per_order(): void
    {
        $buyer = $this->buyer();
        $one = $this->makeOrder($buyer, ['order_number' => 'KDP-UNIQUE-1']);
        $two = $this->makeOrder($buyer, ['order_number' => 'KDP-UNIQUE-2']);

        // Same order => same document number every time (no duplicates by design:
        // the number IS the unique order_number).
        $first = $this->actingAs($buyer)->get('/orders/' . $one->id . '/invoice');
        $second = $this->actingAs($buyer)->get('/orders/' . $one->id . '/invoice');
        $this->assertSame(
            $first->headers->get('content-disposition'),
            $second->headers->get('content-disposition')
        );

        // Different orders => different numbers.
        $other = $this->actingAs($buyer)->get('/orders/' . $two->id . '/invoice');
        $this->assertStringContainsString(
            'invoice-KDP-UNIQUE-1.pdf',
            (string) $first->headers->get('content-disposition')
        );
        $this->assertStringContainsString(
            'invoice-KDP-UNIQUE-2.pdf',
            (string) $other->headers->get('content-disposition')
        );
    }

    public function test_invoice_uses_the_orders_currency_snapshot(): void
    {
        $buyer = $this->buyer();
        // Order placed in USD at checkout rate 0.012: 1000 base => $12.00.
        $order = $this->makeOrder($buyer, [
            'subtotal' => 1000.00, 'total' => 1000.00,
            'currency_code' => 'USD', 'currency_rate' => 0.012,
        ]);
        $this->addItem($order, null, 1000.00, 1);

        $html = app(InvoicePdfService::class)->html($order);

        $this->assertStringContainsString('$12.00', $html);
        $this->assertStringNotContainsString('₹1,000.00', $html);
    }
}
