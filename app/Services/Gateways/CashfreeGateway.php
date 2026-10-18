<?php

namespace App\Services\Gateways;

use App\Models\Order;
use App\Services\PaymentGateway;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Cashfree\Cashfree;
use Cashfree\Model\CreateOrderRequest;
use Cashfree\Model\CustomerDetails;
use Cashfree\Model\OrderMeta;
use Cashfree\Model\OrderCreateRefundRequest;

class CashfreeGateway implements PaymentGateway
{
    private const DEFAULT_API_VERSION = '2025-01-01';

    public function name(): string
    {
        return 'cashfree';
    }

    public function mode(): string
    {
        return config('cashfree.mode', 'test');
    }

    public function createSession(Order $order): array
    {
        $request = new CreateOrderRequest();
        $request->setOrderId('order_' . $order->order_number);
        $request->setOrderAmount((string) round($order->total, 2));
        $request->setOrderCurrency('INR');
        $request->setOrderNote('KDP MART order ' . $order->order_number);

        $customer = new CustomerDetails();
        $customer->setCustomerId((string) $order->customer_email);
        $customer->setCustomerPhone((string) $order->shipping_phone);
        $request->setCustomerDetails($customer);

        $meta = new OrderMeta();
        $meta->setReturnUrl(route('payments.cashfree.return', ['order_id' => $order->id], false));
        $meta->setNotifyUrl(route('payments.webhook', [], false));
        $request->setOrderMeta($meta);

        try {
            $response = $this->client()->PGCreateOrder($request);
            $payload = $this->payloadFromResponse($response);

            $cfOrderId = $payload['cf_order_id'] ?? null;
            $paymentSessionId = $payload['payment_session_id'] ?? null;

            if (! $cfOrderId || ! $paymentSessionId) {
                throw ValidationException::withMessages([
                    'cashfree' => 'Cashfree order creation did not return an order/payment session id.',
                ]);
            }

            // Cashfree's hosted checkout page is opened via a deterministic
            // URL built from the payment session id — the API does not return
            // this URL directly, so we construct it here per environment.
            $host = $this->mode() === 'live'
                ? 'https://checkout.cashfree.com'
                : 'https://sandbox.cashfree.com';

            $checkoutUrl = $host . '/pg/checkout?token=' . urlencode($paymentSessionId);

            return [
                'id' => (string) $cfOrderId,
                'amount' => (int) round($order->total * 100),
                'currency' => 'INR',
                'type' => 'redirect',
                'return_url' => $checkoutUrl,
                'payment_session_id' => $paymentSessionId,
                'cf_order_id' => $cfOrderId,
                'details' => $payload,
            ];
        } catch (\Throwable $e) {
            Log::error('Cashfree order creation failed: ' . $e->getMessage(), [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'amount' => round($order->total, 2),
            ]);

            throw ValidationException::withMessages([
                'cashfree' => 'Unable to create Cashfree payment. Please try again.',
            ]);
        }
    }

    public function verify(Request $request, Order $order): array
    {
        $orderId = $request->input('order_id');
        $paymentId = $request->input('cf_payment_id');

        if (! $orderId || ! $paymentId) {
            throw ValidationException::withMessages([
                'cashfree' => 'Missing Cashfree order/payment identifiers.',
            ]);
        }

        try {
            $response = $this->client()->PGOrderFetchPayment($orderId, $paymentId);

            $payload = $this->payloadFromResponse($response);

            if ((string) ($payload['payment_status'] ?? '') !== 'SUCCESS') {
                throw ValidationException::withMessages([
                    'cashfree' => 'Cashfree payment has not been captured.',
                ]);
            }

            if ((float) ($payload['payment_amount'] ?? 0) !== round($order->total, 2)) {
                throw ValidationException::withMessages([
                    'cashfree' => 'Cashfree payment amount does not match the order total.',
                ]);
            }

            if (strtoupper($payload['payment_currency'] ?? '') !== 'INR') {
                throw ValidationException::withMessages([
                    'cashfree' => 'Cashfree payment currency does not match.',
                ]);
            }

            return [
                'payment_id' => $payload['cf_payment_id'] ?? $paymentId,
                'status' => 'captured',
            ];
        } catch (\Throwable $e) {
            Log::error('Cashfree payment verification failed: ' . $e->getMessage(), [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
            ]);

            throw ValidationException::withMessages([
                'cashfree' => 'Unable to verify the Cashfree payment.',
            ]);
        }
    }

    public function handleWebhook(Request $request, Order $order): array
    {
        $payload = $request->getContent();
        $signature = $request->header('x-webhook-signature');
        $timestamp = $request->header('x-webhook-timestamp');

        if (empty($signature) || empty($timestamp)) {
            throw ValidationException::withMessages([
                'webhook' => 'Missing Cashfree webhook signature or timestamp.',
            ]);
        }

        try {
            $this->client()->PGVerifyWebhookSignature($signature, $payload, $timestamp);
        } catch (\Throwable $e) {
            Log::warning('Cashfree webhook signature verification failed.');
            throw ValidationException::withMessages(['webhook' => 'Invalid webhook signature.']);
        }

        $event = json_decode($payload, true);

        return [
            'event_type' => $event['type'] ?? '',
            'payload' => $event,
        ];
    }

    public function statusForWebhook(string $eventType): ?string
    {
        return match ($eventType) {
            'order.paid' => 'captured',
            'order.failed' => 'failed',
            'payment.captured' => 'captured',
            default => null,
        };
    }

    public function initiateRefund(Order $order, float $amount, string $idempotencyKey): array
    {
        if (! $order->cashfree_order_id) {
            throw ValidationException::withMessages([
                'cashfree' => 'Cashfree order id is not stored for this order.',
            ]);
        }

        $request = new OrderCreateRefundRequest();
        $request->setRefundId('kdp-refund-' . $order->id);
        $request->setRefundAmount((string) round($amount, 2));
        $request->setRefundNote('KDP MART refund for order ' . $order->order_number);

        try {
            $response = $this->client()->PGOrderCreateRefund($order->cashfree_order_id, $request);

            $payload = $this->payloadFromResponse($response);

            return [
                'id' => $payload['cf_refund_id'] ?? null,
                'amount' => (int) round($amount * 100),
                'currency' => 'INR',
                'status' => $payload['refund_status'] ?? 'pending',
            ];
        } catch (\Throwable $e) {
            Log::error('Cashfree refund creation failed: ' . $e->getMessage(), [
                'cashfree_order_id' => $order->cashfree_order_id,
                'amount' => round($amount, 2),
            ]);

            throw ValidationException::withMessages([
                'cashfree_refund' => 'Unable to process refund through Cashfree.',
            ]);
        }
    }

    public function fetchRefundStatus(Order $order, string $providerRefundId): array
    {
        return $this->fetchRefundForOrder($order, $providerRefundId);
    }

    public function fetchRefundForOrder(Order $order, string $refundId): array
    {
        if (! $order->cashfree_order_id) {
            throw ValidationException::withMessages([
                'cashfree' => 'Cashfree order id is not stored for this order.',
            ]);
        }

        try {
            $response = $this->client()->PGOrderFetchRefund($order->cashfree_order_id, $refundId);

            return $this->payloadFromResponse($response);
        } catch (\Throwable $e) {
            Log::error('Cashfree refund fetch failed: ' . $e->getMessage(), [
                'cashfree_order_id' => $order->cashfree_order_id,
                'refund_id' => $refundId,
            ]);

            throw ValidationException::withMessages([
                'cashfree_refund' => 'Unable to verify refund status with the provider.',
            ]);
        }
    }

    private function client(): Cashfree
    {
        $id = config('cashfree.key_id');
        $secret = config('cashfree.key_secret');

        if (empty($id) || empty($secret)) {
            throw ValidationException::withMessages([
                'cashfree' => 'Cashfree credentials are not configured. Set CASHFREE_KEY_ID and CASHFREE_KEY_SECRET.',
            ]);
        }

        $environment = $this->mode() === 'live' ? Cashfree::PRODUCTION : Cashfree::SANDBOX;

        $client = new Cashfree($environment, $id, $secret, '', '', '', true);

        // Override the SDK's built-in default API version if the app's config
        // specifies a different one (e.g. a newer stable release once the
        // merchant account supports it).
        $client->XApiVersion = config('cashfree.api_version', self::DEFAULT_API_VERSION);

        return $client;
    }

    private function payloadFromResponse($response): array
    {
        // The Cashfree SDK's public methods return a 3-element array:
        // [Model, statusCode, headers]. Unwrap it to the model (or raw
        // array) before converting to a plain array for uniform handling.
        if (is_array($response) && isset($response[0]) && is_object($response[0])) {
            $response = $response[0];
        }

        if ($response instanceof \ArrayAccess && ! is_object($response)) {
            return iterator_to_array($response);
        }

        if (is_array($response)) {
            return $response;
        }

        if (is_object($response) && method_exists($response, 'jsonSerialize')) {
            $serialized = $response->jsonSerialize();
            if (is_array($serialized)) {
                return $serialized;
            }
        }

        return get_object_vars($response);
    }
}
