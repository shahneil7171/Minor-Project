<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\OrderStatusService;
use App\Services\RazorpayService;
use App\Mail\OrderConfirmationMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

/**
 * Razorpay payment endpoints for KDP MART.
 *
 * All payment verification happens server-side. The browser's return from
 * Razorpay Checkout is never treated as proof of payment — the signature
 * and provider API are the only trusted sources.
 */
class PaymentController extends Controller
{
    public function __construct(
        private RazorpayService $razorpay,
        private OrderStatusService $statuses,
    ) {}

    /**
     * Create a Razorpay order for an existing pending local order.
     *
     * Called from the checkout frontend after the local order has been
     * persisted as pending. Returns the public details needed to open
     * Razorpay Checkout.
     */
    public function create(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = $request->validate([
            'order_id' => ['required', 'integer', 'exists:orders,id'],
        ]);

        $order = Order::whereKey($data['order_id'])->firstOrFail();

        // Ownership (auth + guest): a signed-in buyer may only pay their own
        // order. A guest order (user_id null) may be paid from the same session
        // that placed it; anything else is rejected without revealing whether
        // the id exists beyond the exists:orders,id check above.
        $user = $request->user();
        if ($user) {
            if ((int) $order->user_id !== (int) $user->id) {
                abort(403);
            }
        } elseif ($order->user_id !== null || (int) session('checkout_order_id', 0) !== (int) $order->id) {
            abort(403);
        }

        // Only razorpay orders can be paid via Razorpay.
        if ($order->payment_provider !== 'razorpay') {
            throw ValidationException::withMessages([
                'order' => 'This order is not a Razorpay order.',
            ]);
        }

        // Only pending (unpaid) orders can be opened for Razorpay payment.
        if ($order->status !== 'pending') {
            throw ValidationException::withMessages([
                'order' => 'This order is not in a payable state.',
            ]);
        }

        // Idempotency: if a Razorpay order ID already exists, reuse it.
        if ($order->razorpay_order_id) {
            return response()->json([
                'key_id'      => config('razorpay.key_id'),
                'order_id'    => $order->razorpay_order_id,
                'amount'      => (int) round((float) $order->total * 100),
                'currency'    => config('razorpay.currency', 'INR'),
                'name'        => 'KDP MART',
                'description' => 'Order #' . $order->order_number,
                'prefill'     => [
                    'name'  => $order->shipping_name,
                    'email' => $order->customer_email,
                    'phone' => $order->shipping_phone,
                ],
                'notes'       => [
                    'kdp_order_number' => $order->order_number,
                    'kdp_order_id'     => (string) $order->id,
                ],
                'theme'       => ['color' => '#10b981'],
            ]);
        }

        // Create the Razorpay order server-side.
        $razorpayOrder = $this->razorpay->createOrder($order);

        // Persist the Razorpay order ID on the local order.
        $order->forceFill(['razorpay_order_id' => $razorpayOrder['id']])->save();

        return response()->json([
            'key_id'      => config('razorpay.key_id'),
            'order_id'    => $razorpayOrder['id'],
            'amount'      => $razorpayOrder['amount'],
            'currency'    => $razorpayOrder['currency'],
            'name'        => 'KDP MART',
            'description' => 'Order #' . $order->order_number,
            'prefill'     => [
                'name'  => $order->shipping_name,
                'email' => $order->customer_email,
                'phone' => $order->shipping_phone,
            ],
            'notes'       => [
                'kdp_order_number' => $order->order_number,
                'kdp_order_id'     => (string) $order->id,
            ],
            'theme'       => ['color' => '#10b981'],
        ]);
    }

    public function verify(Request $request): \Illuminate\Http\RedirectResponse
    {
        $data = $request->validate([
            'order_id' => ['required', 'integer', 'exists:orders,id'],
            'razorpay_order_id' => ['required', 'string'],
            'razorpay_payment_id' => ['required', 'string'],
            'razorpay_signature'  => ['required', 'string'],
        ]);

        $this->razorpay->verifySignature(
            $data['razorpay_order_id'],
            $data['razorpay_payment_id'],
            $data['razorpay_signature'],
        );

        $order = Order::whereKey($data['order_id'])
            ->where('razorpay_order_id', $data['razorpay_order_id'])
            ->first();

        if (! $order) {
            Log::warning('Razorpay callback for unknown order', [
                'razorpay_order_id' => $data['razorpay_order_id'],
                'razorpay_payment_id' => $data['razorpay_payment_id'],
            ]);

            throw ValidationException::withMessages([
                'razorpay' => 'Payment references an unknown order.',
            ]);
        }

        $user = $request->user();
        if ($user) {
            if ((int) $order->user_id !== (int) $user->id) {
                abort(403);
            }
        } elseif ($order->user_id !== null || (int) session('checkout_order_id', 0) !== (int) $order->id) {
            abort(403);
        }

        if ($order->payment_status === 'captured' && $order->razorpay_payment_id === $data['razorpay_payment_id']) {
            return redirect()->route('checkout.complete');
        }

        $payment = $this->razorpay->fetchPayment($data['razorpay_payment_id']);
        $this->razorpay->verifyPaymentViaApi($payment, $order, $data['razorpay_order_id']);

        DB::transaction(function () use ($order, $data) {
            $order->markRazorpayCaptured($data['razorpay_payment_id']);

            // Payment verification is authoritative: the provider (signature +
            // fetch) is the actor, never the buyer's role — buyers cannot move
            // orders to confirmed themselves (OrderStatusService only lets
            // buyers cancel).
            $this->statuses->transition($order, 'confirmed', null, 'Payment verified via Razorpay.');
        });

        $order->refresh();
        try {
            Mail::to($order->customer_email)->send(new OrderConfirmationMail($order));
        } catch (\Throwable $e) {
            Log::error('Order confirmation mail failed: ' . $e->getMessage(), [
                'order_id' => $order->id,
            ]);
        }

        session()->forget('checkout_coupon_code');
        session()->forget('buy_now_item');
        app(\App\Services\CartService::class)->clear();

        return redirect()->route('checkout.complete')
            ->with('success', 'Payment verified successfully. Order # ' . $order->order_number . ' is confirmed.');
    }

    /**
     * The gateway payment page: shows the pending order, then opens Razorpay
     * Checkout via the server-created order (payments.create). The posted
     * payment result is verified server-side (payments.verify) — the browser
     * is never trusted.
     */
    public function show(Request $request, Order $order): \Illuminate\View\View|\Illuminate\Http\RedirectResponse
    {
        $user = $request->user();
        if ($user) {
            if ((int) $order->user_id !== (int) $user->id) {
                abort(403);
            }
        } elseif ($order->user_id !== null || (int) session('checkout_order_id', 0) !== (int) $order->id) {
            abort(403);
        }

        if ($order->payment_provider !== 'razorpay') {
            return redirect()->route('checkout.complete');
        }

        if ($order->payment_status === 'captured') {
            return redirect()->route('checkout.complete');
        }

        if ($order->status !== 'pending') {
            return redirect()->route('checkout.complete');
        }

        return view('payments.show', compact('order'));
    }

    public function webhook(Request $request): \Illuminate\Http\JsonResponse
    {
        $payload = $request->getContent();
        $signature = $request->header('X-Razorpay-Signature');

        try {
            $this->razorpay->verifyWebhookSignature($payload, $signature);
        } catch (ValidationException $e) {
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        $event = json_decode($payload, true);
        $eventId = $event['id'] ?? null;
        $eventType = $event['event'] ?? null;

        if (! $eventId || ! $eventType) {
            return response()->json(['error' => 'Invalid payload'], 400);
        }

        $lockKey = 'razorpay-webhook:' . $eventId;
        if (! Cache::add($lockKey, 'processed', 86400)) {
            return response()->json(['status' => 'duplicate'], 200);
        }

        try {
            $this->handleWebhookEvent($eventType, $event['payload'] ?? []);
        } catch (\Throwable $e) {
            Log::error('Razorpay webhook handling failed: ' . $e->getMessage(), [
                'event_id' => $eventId,
                'event_type' => $eventType,
            ]);

            return response()->json(['error' => 'Processing failed'], 500);
        }

        return response()->json(['status' => 'ok'], 200);
    }

    private function handleWebhookEvent(string $eventType, array $payload): void
    {
        $entity = $payload['entity'] ?? [];

        match ($eventType) {
            'payment.captured' => $this->handlePaymentCaptured($entity),
            'payment.failed'   => $this->handlePaymentFailed($entity),
            default            => Log::info('Unhandled Razorpay event: ' . $eventType),
        };
    }

    private function handlePaymentCaptured(array $entity): void
    {
        $razorpayOrderId = $entity['order_id'] ?? null;
        $razorpayPaymentId = $entity['id'] ?? null;

        if (! $razorpayOrderId || ! $razorpayPaymentId) {
            Log::warning('Razorpay payment.captured missing fields.');
            return;
        }

        $order = Order::where('razorpay_order_id', $razorpayOrderId)->first();

        if (! $order) {
            Log::warning('Razorpay webhook for unknown order', ['razorpay_order_id' => $razorpayOrderId]);
            return;
        }

        if ($order->payment_status === 'captured') {
            return;
        }

        try {
            $payment = $this->razorpay->fetchPayment($razorpayPaymentId);
            $this->razorpay->verifyPaymentViaApi($payment, $order, $razorpayOrderId);
        } catch (ValidationException $e) {
            Log::error('Webhook payment verification failed: ' . $e->getMessage(), [
                'razorpay_payment_id' => $razorpayPaymentId,
            ]);
            return;
        }

        DB::transaction(function () use ($order, $razorpayPaymentId) {
            $order->markRazorpayCaptured($razorpayPaymentId);

            // Webhook is authoritative (signed payload + verified fetch), so it
            // confirms as the system — never as the buyer's role.
            $this->statuses->transition($order, 'confirmed', null, 'Payment verified via Razorpay webhook.');
        });

        $order->refresh();
        try {
            Mail::to($order->customer_email)->send(new OrderConfirmationMail($order));
        } catch (\Throwable $e) {
            Log::error('Order confirmation mail failed (webhook): ' . $e->getMessage(), [
                'order_id' => $order->id,
            ]);
        }
    }

    private function handlePaymentFailed(array $entity): void
    {
        $razorpayOrderId = $entity['order_id'] ?? null;

        if (! $razorpayOrderId) {
            Log::warning('Razorpay payment.failed missing order_id.');
            return;
        }

        $order = Order::where('razorpay_order_id', $razorpayOrderId)->first();

        if (! $order) {
            Log::warning('Razorpay webhook for unknown order', ['razorpay_order_id' => $razorpayOrderId]);
            return;
        }

        $order->markRazorpayFailed();

        Log::info('Razorpay payment failed for order', [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
        ]);
    }
}