<?php

namespace App\Services\Gateways;

use App\Models\Order;
use App\Services\PaymentGateway;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Razorpay\Api\Api;
use Razorpay\Api\Errors\SignatureVerificationError;

class RazorpayGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'razorpay';
    }

    public function mode(): string
    {
        return config('razorpay.mode', 'test');
    }

    public function createSession(Order $order): array
    {
        $amountInPaise = (int) round((float) $order->total * 100);

        $data = [
            'amount'          => $amountInPaise,
            'currency'        => config('razorpay.currency', 'INR'),
            'receipt'         => 'order_' . $order->order_number,
            'notes'           => [
                'kdp_order_id'     => (string) $order->id,
                'kdp_order_number' => $order->order_number,
                'kdp_customer_email' => $order->customer_email ?? '',
                'kdp_amount'       => (string) $order->total,
            ],
        ];

        try {
            $razorpayOrder = app(Api::class)->order->create($data);

            return [
                'id'         => $razorpayOrder['id'],
                'amount'     => (int) $razorpayOrder['amount'],
                'currency'   => $razorpayOrder['currency'],
                'receipt'    => $razorpayOrder['receipt'],
                'type'       => 'script',
                'key_id'     => config('razorpay.key_id'),
                'name'       => 'KDP MART',
                'description' => 'Order #' . $order->order_number,
                'prefill'    => [
                    'name'  => $order->shipping_name,
                    'email' => $order->customer_email,
                    'phone' => $order->shipping_phone,
                ],
                'notes'      => $data['notes'],
                'theme'      => ['color' => '#10b981'],
            ];
        } catch (\Exception $e) {
            Log::error('Razorpay session creation failed: ' . $e->getMessage(), [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'amount' => $amountInPaise,
            ]);

            throw ValidationException::withMessages([
                'razorpay' => 'Unable to create payment order. Please try again.',
            ]);
        }
    }
    public function verify(Request $request, Order $order): array
    {
        $data = $request->validate([
            'razorpay_order_id'   => ['required', 'string'],
            'razorpay_payment_id' => ['required', 'string'],
            'razorpay_signature'  => ['required', 'string'],
        ]);

        $this->verifySignature(
            $data['razorpay_order_id'],
            $data['razorpay_payment_id'],
            $data['razorpay_signature']
        );

        $payment = $this->fetchPayment($data['razorpay_payment_id']);
        $this->verifyPaymentViaApi($payment, $order, $data['razorpay_order_id']);

        return [
            'payment_id' => $data['razorpay_payment_id'],
            'status' => 'captured',
        ];
    }
    public function handleWebhook(Request $request, Order $order): array
    {
        $payload = $request->getContent();
        $signature = $request->header('X-Razorpay-Signature');

        $this->verifyWebhookSignature($payload, $signature);

        $event = json_decode($payload, true);

        if (! is_array($event)) {
            throw ValidationException::withMessages(['webhook' => 'Invalid webhook payload.']);
        }

        return [
            'event_type' => $event['event'] ?? '',
            'payload' => $event,
        ];
    }

    public function statusForWebhook(string $eventType): ?string
    {
        return match ($eventType) {
            'payment.captured' => 'captured',
            'payment.failed' => 'failed',
            default => null,
        };
    }
    public function initiateRefund(Order $order, float $amount, string $idempotencyKey): array
    {
        if (! $order->razorpay_payment_id) {
            throw ValidationException::withMessages([
                'razorpay' => 'This order has no Razorpay payment id to refund.',
            ]);
        }

        $amountInPaise = (int) round($amount * 100);

        try {
            $refund = app(Api::class)->payment->refund(
                $order->razorpay_payment_id,
                ['amount' => $amountInPaise],
                ['idempotency_key' => $idempotencyKey]
            );

            return [
                'id' => $refund['id'],
                'amount' => (int) $refund['amount'],
                'currency' => $refund['currency'],
                'status' => $refund['status'] ?? 'pending',
            ];
        } catch (\Exception $e) {
            Log::error('Razorpay refund creation failed: ' . $e->getMessage(), [
                'razorpay_payment_id' => $order->razorpay_payment_id,
                'amount' => $amountInPaise,
            ]);

            throw ValidationException::withMessages([
                'razorpay_refund' => 'Unable to process refund through the provider.',
            ]);
        }
    }
    public function fetchRefundStatus(Order $order, string $providerRefundId): array
    {
        if (! $order->razorpay_payment_id) {
            throw ValidationException::withMessages([
                'razorpay_refund' => 'This order has no Razorpay payment id to fetch a refund against.',
            ]);
        }

        try {
            $refund = app(Api::class)->payment->fetch($order->razorpay_payment_id)->refund($providerRefundId);

            return [
                'id' => $refund['id'] ?? null,
                'amount' => $refund['amount'] ?? null,
                'status' => $refund['status'] ?? null,
            ];
        } catch (\Exception $e) {
            Log::error('Razorpay refund fetch failed: ' . $e->getMessage(), [
                'razorpay_refund_id' => $providerRefundId,
                'order_id' => $order->id,
            ]);

            throw ValidationException::withMessages([
                'razorpay_refund' => 'Unable to verify refund status with the provider.',
            ]);
        }
    }

    /** @return mixed */
    public function fetchPayment(string $razorpayPaymentId)
    {
        try {
            return app(Api::class)->payment->fetch($razorpayPaymentId);
        } catch (\Exception $e) {
            Log::error('Razorpay payment fetch failed: ' . $e->getMessage(), [
                'razorpay_payment_id' => $razorpayPaymentId,
            ]);

            throw ValidationException::withMessages([
                'razorpay' => 'Unable to fetch the payment from the provider.',
            ]);
        }
    }
    public function verifySignature(
        string $razorpayOrderId,
        string $razorpayPaymentId,
        string $razorpaySignature
    ): void {
        $expected = hash_hmac(
            'sha256',
            $razorpayOrderId . '|' . $razorpayPaymentId,
            config('razorpay.key_secret')
        );

        if (! hash_equals($expected, $razorpaySignature)) {
            Log::warning('Razorpay signature verification failed.');
            throw ValidationException::withMessages(['webhook' => 'Invalid payment signature.']);
        }
    }

    /** @param mixed $payment */
    public function verifyPaymentViaApi($payment, Order $order, string $razorpayOrderId): void
    {
        if (! isset($payment['amount'])) {
            throw ValidationException::withMessages(['razorpay' => 'Payment payload is missing the amount.']);
        }

        if ((int) $payment['amount'] !== (int) round((float) $order->total * 100)) {
            throw ValidationException::withMessages([
                'razorpay' => 'Payment amount does not match the order total.',
                'expected' => (int) round((float) $order->total * 100),
                'actual' => $payment['amount'] ?? null,
            ]);
        }

        if (strtoupper($payment['currency'] ?? '') !== strtoupper(config('razorpay.currency', 'INR'))) {
            throw ValidationException::withMessages([
                'razorpay' => 'Payment currency does not match.',
            ]);
        }

        if ((string) ($payment['status'] ?? '') !== 'captured') {
            Log::warning('Razorpay payment not captured', [
                'status' => $payment['status'] ?? null,
            ]);

            throw ValidationException::withMessages([
                'razorpay' => 'Payment has not been captured by the provider.',
            ]);
        }
    }

    public function verifyWebhookSignature(string $payload, string $signature): void
    {
        $secret = config('razorpay.webhook_secret');

        if (empty($secret)) {
            Log::error('Razorpay webhook secret is not configured.');
            throw ValidationException::withMessages(['webhook' => 'Webhook secret not configured.']);
        }

        try {
            app(Api::class)->utility->verifyWebhookSignature($payload, $signature, $secret);
        } catch (SignatureVerificationError $e) {
            Log::warning('Razorpay webhook signature verification failed.');
            throw ValidationException::withMessages(['webhook' => 'Invalid webhook signature.']);
        } catch (\Exception $e) {
            Log::error('Razorpay webhook verification error: ' . $e->getMessage());
            throw ValidationException::withMessages(['webhook' => 'Webhook verification encountered an error.']);
        }
    }
}
