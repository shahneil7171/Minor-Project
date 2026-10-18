<?php

namespace App\Http\Controllers;

use App\Mail\OrderConfirmationMail;
use App\Models\Order;
use App\Services\CartService;
use App\Services\OrderStatusService;
use App\Services\PaymentGateway;
use App\Services\PaymentGatewayFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

/**
 * Gateway-agnostic payment endpoints for KDP MART (Razorpay + Cashfree).
 *
 * All payment verification happens server-side. The browser's return from a
 * gateway checkout is never treated as proof of payment — the gateway's own
 * signature + provider API are the only trusted sources, for BOTH providers.
 */
class PaymentController extends Controller
{
    public function __construct(
        private OrderStatusService $statuses,
    ) {}

    /**
     * Create a gateway order/session for an existing pending local order.
     *
     * Called from the checkout frontend after the local order has been
     * persisted as pending. Returns the public details needed to open the
     * appropriate hosted checkout (Razorpay script config, or Cashfree
     * redirect URL).
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
        $this->authorizePayer($request, $order);

        // Only gateway orders (razorpay / cashfree) can be paid online.
        if (! $order->isGateway()) {
            throw ValidationException::withMessages([
                'order' => 'This order is not an online-payment order.',
            ]);
        }

        // Only pending (unpaid) orders can be opened for gateway payment.
        if ($order->status !== 'pending') {
            throw ValidationException::withMessages([
                'order' => 'This order is not in a payable state.',
            ]);
        }

        $gateway = PaymentGatewayFactory::forOrder($order);

        // Idempotency: if this order already has a provider-side session,
        // reuse it instead of creating a duplicate chargeable session.
        if ($this->providerOrderId($order) !== null) {
            return response()->json($this->sessionPayloadForExistingOrder($order, $gateway));
        }

        // Create the provider session/order server-side.
        $session = $gateway->createSession($order);

        // Persist the provider's order id on the local order.
        $this->persistProviderOrderId($order, $gateway->name(), $session['id']);

        return response()->json($session);
    }

    /**
     * Verify a Razorpay checkout result posted back by the browser.
     *
     * Razorpay is a script-based flow: the browser posts
     * razorpay_order_id / razorpay_payment_id / razorpay_signature back to
     * us. Cashfree uses a redirect flow instead and is handled by
     * cashfreeReturn() below.
     */
    public function verify(Request $request): \Illuminate\Http\RedirectResponse
    {
        $data = $request->validate([
            'order_id' => ['required', 'integer', 'exists:orders,id'],
            'razorpay_order_id' => ['required', 'string'],
            'razorpay_payment_id' => ['required', 'string'],
            'razorpay_signature'  => ['required', 'string'],
        ]);

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

        $this->authorizePayer($request, $order);

        $gateway = PaymentGatewayFactory::forOrder($order);
        $result = $gateway->verify($request, $order);

        // Already captured with this exact payment id — nothing to do.
        if ($order->payment_status === 'captured'
            && $order->razorpay_payment_id === $result['payment_id']) {
            return redirect()->route('checkout.complete');
        }

        $this->captureAndConfirm(
            $order,
            $result['payment_id'],
            'Payment verified via ' . ucfirst($gateway->name()) . '.'
        );

        return redirect()->route('checkout.complete')
            ->with('success', 'Payment verified successfully. Order # ' . $order->order_number . ' is confirmed.');
    }

    /**
     * Cashfree redirect-flow return handler.
     *
     * Cashfree redirects the buyer back to the return_url we supplied in
     * OrderMeta (payments.cashfree.return), passing `order_id` (Cashfree's
     * own id) and `cf_payment_id` as query parameters. The browser is never
     * trusted — the payment is re-verified server-side via the Cashfree API
     * before the order is marked captured.
     */
    public function cashfreeReturn(Request $request): \Illuminate\Http\RedirectResponse
    {
        $data = $request->validate([
            'order_id' => ['required', 'string'],
            'cf_payment_id' => ['nullable', 'string'],
        ]);

        $order = Order::where('cashfree_order_id', $data['order_id'])->first();

        if (! $order) {
            Log::warning('Cashfree return for unknown order', [
                'cashfree_order_id' => $data['order_id'],
            ]);

            throw ValidationException::withMessages([
                'cashfree' => 'Payment references an unknown order.',
            ]);
        }

        $this->authorizePayer($request, $order);

        // Idempotent: already captured, skip re-verification.
        if ($order->payment_status === 'captured') {
            return redirect()->route('checkout.complete');
        }

        $gateway = PaymentGatewayFactory::forOrder($order);
        $result = $gateway->verify($request, $order);

        $this->captureAndConfirm(
            $order,
            $result['payment_id'],
            'Payment verified via ' . ucfirst($gateway->name()) . '.'
        );

        return redirect()->route('checkout.complete')
            ->with('success', 'Payment verified successfully. Order # ' . $order->order_number . ' is confirmed.');
    }

    /**
     * The gateway payment page: shows the pending order, then opens the
     * provider's checkout (Razorpay script, or Cashfree redirect) via the
     * server-created session (payments.create). The posted/returned payment
     * result is verified server-side (payments.verify /
     * payments.cashfree.return) — the browser is never trusted.
     */
    public function show(Request $request, Order $order): \Illuminate\View\View|\Illuminate\Http\RedirectResponse
    {
        $this->authorizePayer($request, $order);

        if (! $order->isGateway()) {
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

    /**
     * Shared webhook endpoint for BOTH Razorpay and Cashfree.
     *
     * Each provider signs its webhooks differently (Razorpay: HMAC-SHA256 of
     * the raw body with the webhook secret, exposed as X-Razorpay-Signature;
     * Cashfree: HMAC-SHA256 of timestamp.rawbody with the client secret,
     * exposed as x-webhook-signature + x-webhook-timestamp). We detect which
     * provider is calling from the headers present, then dispatch to that
     * provider's gateway adapter for signature verification and event
     * parsing before touching any local state.
     */
    public function webhook(Request $request): \Illuminate\Http\JsonResponse
    {
        $provider = $this->detectWebhookProvider($request);

        if ($provider === null) {
            return response()->json(['error' => 'Unrecognized webhook'], 400);
        }

        $gateway = PaymentGatewayFactory::for($provider);

        try {
            $order = $this->orderForWebhook($provider, $request);
            $event = $gateway->handleWebhook($request, $order ?? new Order());
        } catch (ValidationException $e) {
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        $eventType = $event['event_type'] ?? null;
        $eventId = $this->webhookEventId($provider, $event);

        if (! $eventType) {
            return response()->json(['error' => 'Invalid payload'], 400);
        }

        // Provider-level idempotency: providers may retry webhooks; only the
        // first delivery for a given event id is processed.
        $lockKey = $provider . '-webhook:' . $eventId;
        if (! Cache::add($lockKey, 'processed', 86400)) {
            return response()->json(['status' => 'duplicate'], 200);
        }

        try {
            $this->handleWebhookEvent($gateway, $order, $eventType, $event['payload'] ?? []);
        } catch (\Throwable $e) {
            Log::error(ucfirst($provider) . ' webhook handling failed: ' . $e->getMessage(), [
                'event_id' => $eventId,
                'event_type' => $eventType,
            ]);

            return response()->json(['error' => 'Processing failed'], 500);
        }

        return response()->json(['status' => 'ok'], 200);
    }

    /**
     * Ownership check (auth + guest) shared by every buyer-facing endpoint.
     */
    private function authorizePayer(Request $request, Order $order): void
    {
        $user = $request->user();
        if ($user) {
            if ((int) $order->user_id !== (int) $user->id) {
                abort(403);
            }
        } elseif ($order->user_id !== null || (int) session('checkout_order_id', 0) !== (int) $order->id) {
            abort(403);
        }
    }

    /**
     * The provider's own order/session id stored on this local order, if any.
     */
    private function providerOrderId(Order $order): ?string
    {
        return match ($order->payment_provider) {
            'razorpay' => $order->razorpay_order_id,
            'cashfree' => $order->cashfree_order_id,
            default => null,
        };
    }

    /**
     * Persist the provider's order id on the local order (provider-agnostic).
     */
    private function persistProviderOrderId(Order $order, string $provider, string $providerOrderId): void
    {
        $column = $provider === 'cashfree' ? 'cashfree_order_id' : 'razorpay_order_id';
        $order->forceFill([$column => $providerOrderId])->save();
    }

    /**
     * Rebuild the session payload when a provider order already exists, so
     * retrying payments.create is idempotent and does not create a second
     * chargeable session at the provider.
     */
    private function sessionPayloadForExistingOrder(Order $order, PaymentGateway $gateway): array
    {
        if ($gateway->name() === 'razorpay') {
            return [
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
            ];
        }

        // Cashfree (and any future redirect-style provider): the return URL is
        // deterministic per-order; the provider will reject a second payment
        // attempt on an already-paid order, and our cashfreeReturn() handler
        // re-verifies server-side regardless, so reusing it is safe.
        return [
            'id' => $this->providerOrderId($order),
            'type' => 'redirect',
            'return_url' => route('payments.cashfree.return', ['order_id' => $this->providerOrderId($order)]),
        ];
    }

    /**
     * Mark the order captured (provider-specific column) and confirm it.
     *
     * Shared by verify() / cashfreeReturn() / webhook handling so every path
     * transitions the order, records the payment id and sends the confirmation
     * email identically.
     */
    private function captureAndConfirm(Order $order, string $providerPaymentId, string $note): void
    {
        DB::transaction(function () use ($order, $providerPaymentId, $note) {
            $order->markPaymentCaptured($providerPaymentId);

            // Payment verification is authoritative: the provider (signature +
            // fetch) is the actor, never the buyer's role — buyers cannot move
            // orders to confirmed themselves (OrderStatusService only lets
            // buyers cancel).
            $this->statuses->transition($order, 'confirmed', null, $note);
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
        app(CartService::class)->clear();
    }

    /**
     * Detect which provider sent this webhook from its signature headers.
     */
    private function detectWebhookProvider(Request $request): ?string
    {
        if ($request->header('X-Razorpay-Signature')) {
            return 'razorpay';
        }

        if ($request->header('x-webhook-signature') && $request->header('x-webhook-timestamp')) {
            return 'cashfree';
        }

        return null;
    }

    /**
     * Find the local order (if any) this webhook refers to, before signature
     * verification — used only to give the gateway adapter an Order to bind
     * against; it is not trusted for any state change until the signature
     * check inside handleWebhook() passes.
     */
    private function orderForWebhook(string $provider, Request $request): ?Order
    {
        $payload = json_decode($request->getContent(), true);

        if ($provider === 'razorpay') {
            $razorpayOrderId = $payload['payload']['payment']['entity']['order_id'] ?? null;

            return $razorpayOrderId
                ? Order::where('razorpay_order_id', $razorpayOrderId)->first()
                : null;
        }

        // Cashfree: the order id appears as `data.order.order_id` in the
        // signed payload for most event types (order.paid, order.failed, ...).
        $cashfreeOrderId = $payload['data']['order']['order_id'] ?? null;

        return $cashfreeOrderId
            ? Order::where('cashfree_order_id', $cashfreeOrderId)->first()
            : null;
    }

    /**
     * Extract a stable event id for webhook deduplication, per provider.
     */
    private function webhookEventId(string $provider, array $event): string
    {
        if ($provider === 'razorpay') {
            return (string) ($event['payload']['id'] ?? ($event['payload']['event'] ?? 'unknown'));
        }

        // Cashfree webhooks do not carry a top-level event id; the
        // timestamped webhook id under `data.webhook_id` is stable per
        // delivery attempt and unique per event.
        return (string) ($event['payload']['data']['webhook_id'] ?? md5($event['payload']['type'] ?? 'unknown'));
    }

    /**
     * Dispatch a verified webhook event to the shared capture/fail logic,
     * regardless of which provider sent it.
     */
    private function handleWebhookEvent(
        PaymentGateway $gateway,
        ?Order $order,
        string $eventType,
        array $payload
    ): void {
        $status = $gateway->statusForWebhook($eventType);

        if ($status === null || $order === null) {
            Log::info(ucfirst($gateway->name()) . ' webhook ignored: ' . $eventType, [
                'order_found' => $order !== null,
            ]);
            return;
        }

        if ($gateway->name() === 'razorpay') {
            // Razorpay: re-verify the payment against the provider API before
            // marking captured — the signed payload alone is not enough (the
            // signature only proves Razorpay sent *a* webhook, not that the
            // payment still matches our order amount/currency).
            $entity = $payload['entity'] ?? [];
            $razorpayPaymentId = $entity['id'] ?? null;

            if ($status === 'captured') {
                if ($order->payment_status === 'captured' || ! $razorpayPaymentId) {
                    return;
                }

                /** @var \App\Services\Gateways\RazorpayGateway $gateway */
                $payment = $gateway->fetchPayment($razorpayPaymentId);
                $gateway->verifyPaymentViaApi($payment, $order, $entity['order_id'] ?? $order->razorpay_order_id);

                $this->captureAndConfirm($order, $razorpayPaymentId, 'Payment verified via Razorpay webhook.');
            } elseif ($status === 'failed') {
                $order->markRazorpayFailed();

                Log::info('Razorpay payment failed for order (webhook)', [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                ]);
            }

            return;
        }

        // Cashfree (and any future provider with a similar event shape).
        if ($status === 'captured') {
            if ($order->payment_status === 'captured') {
                return;
            }

            $cfPaymentId = $payload['payment']['cf_payment_id']
                ?? ($payload['data']['payment']['cf_payment_id'] ?? null);

            if (! $cfPaymentId) {
                Log::warning('Cashfree order.paid webhook missing cf_payment_id.');
                return;
            }

            $this->captureAndConfirm($order, (string) $cfPaymentId, 'Payment verified via Cashfree webhook.');
        } elseif ($status === 'failed') {
            $order->markCashfreeFailed();

            Log::info('Cashfree payment failed for order (webhook)', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
            ]);
        }
    }
}
