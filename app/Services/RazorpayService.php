<?php

namespace App\Services;

use App\Models\Order;
use Razorpay\Api\Api;
use Razorpay\Api\Errors\SignatureVerificationError;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Razorpay payment-gateway integration for KDP MART.
 *
 * All secret operations happen server-side. The public key ID is the only
 * value ever sent to the frontend (for Razorpay Checkout). The secret key
 * and webhook secret never leave the server.
 *
 * TEST MODE is the default. Live mode requires explicit merchant activation
 * and must never be enabled automatically.
 */
class RazorpayService
{
    /** @var Api|null */
    protected $api;

    /**
     * Initialize the Razorpay API client using configured credentials.
     *
     * @throws ValidationException if credentials are missing.
     */
    public function __construct()
    {
        $keyId = config('razorpay.key_id');
        $keySecret = config('razorpay.key_secret');

        if (empty($keyId) || empty($keySecret)) {
            throw ValidationException::withMessages([
                'razorpay' => 'Razorpay credentials are not configured. Set RAZORPAY_KEY_ID and RAZORPAY_KEY_SECRET.',
            ]);
        }

        $this->api = new Api($keyId, $keySecret);
    }

    /**
     * Return the underlying Razorpay API client instance.
     */
    public function client(): Api
    {
        return $this->api;
    }

    /**
     * Whether Razorpay is running in test mode.
     */
    public function isTestMode(): bool
    {
        return config('razorpay.mode', 'test') === 'test';
    }

    /**
     * Create a Razorpay order server-side for the given local order.
     *
     * The amount is the final payable amount in INR paise (smallest currency
     * unit). A unique receipt references the local order number.
     *
     * @return array{id: string, amount: int, currency: string, receipt: string}
     * @throws ValidationException if Razorpay API call fails.
     */
    public function createOrder(Order $order): array
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
            $razorpayOrder = $this->api->order->create($data);

            return [
                'id'      => $razorpayOrder['id'],
                'amount'  => (int) $razorpayOrder['amount'],
                'currency'=> $razorpayOrder['currency'],
                'receipt' => $razorpayOrder['receipt'],
            ];
        } catch (\Exception $e) {
            Log::error('Razorpay order creation failed: ' . $e->getMessage(), [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'amount'   => $amountInPaise,
            ]);

            throw ValidationException::withMessages([
                'razorpay' => 'Unable to create payment order. Please try again.',
            ]);
        }
    }

    /**
     * Verify the Razorpay payment signature.
     *
     * Razorpay sends razorpay_order_id, razorpay_payment_id and
     * razorpay_signature from the frontend. We verify the signature using
     * the server-stored secret and the Razorpay order ID we created.
     *
     * @throws ValidationException if verification fails.
     */
    public function verifySignature(string $razorpayOrderId, string $razorpayPaymentId, string $razorpaySignature): void
    {
        try {
            $this->api->utility->verifyPaymentSignature([
                'razorpay_order_id'  => $razorpayOrderId,
                'razorpay_payment_id' => $razorpayPaymentId,
                'razorpay_signature' => $razorpaySignature,
            ]);
        } catch (SignatureVerificationError $e) {
            Log::warning('Razorpay signature verification failed', [
                'razorpay_order_id' => $razorpayOrderId,
                'razorpay_payment_id' => $razorpayPaymentId,
            ]);

            throw ValidationException::withMessages([
                'razorpay' => 'Payment verification failed. The payment could not be confirmed.',
            ]);
        } catch (\Exception $e) {
            Log::error('Razorpay signature verification error: ' . $e->getMessage(), [
                'razorpay_order_id' => $razorpayOrderId,
                'razorpay_payment_id' => $razorpayPaymentId,
            ]);

            throw ValidationException::withMessages([
                'razorpay' => 'Payment verification encountered an error. Please contact support.',
            ]);
        }
    }

    /**
     * Fetch a Razorpay payment by its ID to confirm status and amount.
     *
     * @return array
     * @throws ValidationException if the payment cannot be fetched.
     */
    public function fetchPayment(string $razorpayPaymentId): array
    {
        try {
            return $this->api->payment->fetch($razorpayPaymentId);
        } catch (\Exception $e) {
            Log::error('Razorpay payment fetch failed: ' . $e->getMessage(), [
                'razorpay_payment_id' => $razorpayPaymentId,
            ]);

            throw ValidationException::withMessages([
                'razorpay' => 'Unable to verify payment status with the provider.',
            ]);
        }
    }

    /**
     * Verify a payment through the provider API (trusted source, not the browser).
     *
     * Checks that:
     *   - the payment exists,
     *   - the payment references the correct Razorpay order,
     *   - the amount and currency match the expected payable amount,
     *   - the payment status is 'captured'.
     *
     * @throws ValidationException if any check fails.
     */
    public function verifyPaymentViaApi(array $payment, Order $order, string $expectedRazorpayOrderId): void
    {
        // Confirm the payment references the correct Razorpay order.
        if ((string) ($payment['order_id'] ?? '') !== $expectedRazorpayOrderId) {
            Log::warning('Razorpay payment references wrong order', [
                'expected_order' => $expectedRazorpayOrderId,
                'actual_order' => $payment['order_id'] ?? null,
            ]);

            throw ValidationException::withMessages([
                'razorpay' => 'Payment does not match the expected order.',
            ]);
        }

        // Confirm the amount matches (amount is in paise).
        $expectedAmount = (int) round((float) $order->total * 100);
        $actualAmount = (int) ($payment['amount'] ?? 0);

        if ($actualAmount !== $expectedAmount) {
            Log::warning('Razorpay payment amount mismatch', [
                'expected_paise' => $expectedAmount,
                'actual_paise' => $actualAmount,
            ]);

            throw ValidationException::withMessages([
                'razorpay' => 'Payment amount does not match the order total.',
            ]);
        }

        // Confirm the currency matches.
        if ((string) ($payment['currency'] ?? '') !== config('razorpay.currency', 'INR')) {
            Log::warning('Razorpay payment currency mismatch', [
                'expected' => config('razorpay.currency', 'INR'),
                'actual' => $payment['currency'] ?? null,
            ]);

            throw ValidationException::withMessages([
                'razorpay' => 'Payment currency does not match.',
            ]);
        }

        // Confirm the payment has been captured.
        if ((string) ($payment['status'] ?? '') !== 'captured') {
            Log::warning('Razorpay payment not captured', [
                'status' => $payment['status'] ?? null,
            ]);

            throw ValidationException::withMessages([
                'razorpay' => 'Payment has not been captured by the provider.',
            ]);
        }
    }

    /**
     * Create a refund through the Razorpay API.
     *
     * Idempotent: passing the same $idempotencyKey prevents duplicate
     * refund requests to the provider.
     *
     * @return array{id: string, amount: int, currency: string, status: string}
     * @throws ValidationException if the refund request fails.
     */
    public function createRefund(string $razorpayPaymentId, int $amountInPaise, string $idempotencyKey): array
    {
        try {
            $refund = $this->api->payment->refund($razorpayPaymentId, [
                'amount' => $amountInPaise,
            ], ['idempotency_key' => $idempotencyKey]);

            return [
                'id'      => $refund['id'],
                'amount'  => (int) $refund['amount'],
                'currency'=> $refund['currency'],
                'status'  => $refund['status'],
            ];
        } catch (\Exception $e) {
            Log::error('Razorpay refund creation failed: ' . $e->getMessage(), [
                'razorpay_payment_id' => $razorpayPaymentId,
                'amount' => $amountInPaise,
            ]);

            throw ValidationException::withMessages([
                'razorpay_refund' => 'Unable to process refund through the provider.',
            ]);
        }
    }

    /**
     * Fetch a Razorpay refund by its ID to check status.
     *
     * @return array
     * @throws ValidationException if the refund cannot be fetched.
     */
    public function fetchRefund(string $razorpayRefundId): array
    {
        try {
            return $this->api->payment->fetchRefund($razorpayRefundId);
        } catch (\Exception $e) {
            Log::error('Razorpay refund fetch failed: ' . $e->getMessage(), [
                'razorpay_refund_id' => $razorpayRefundId,
            ]);

            throw ValidationException::withMessages([
                'razorpay_refund' => 'Unable to verify refund status with the provider.',
            ]);
        }
    }

    /**
     * Validate the Razorpay webhook signature.
     *
     * Uses the exact raw request body as required by Razorpay.
     *
     * @throws ValidationException if the signature is invalid.
     */
    public function verifyWebhookSignature(string $payload, string $signature): void
    {
        $secret = config('razorpay.webhook_secret');

        if (empty($secret)) {
            Log::error('Razorpay webhook secret is not configured.');
            throw ValidationException::withMessages([
                'webhook' => 'Webhook secret not configured.',
            ]);
        }

        try {
            $this->api->utility->verifyWebhookSignature($payload, $signature, $secret);
        } catch (SignatureVerificationError $e) {
            Log::warning('Razorpay webhook signature verification failed.');
            throw ValidationException::withMessages([
                'webhook' => 'Invalid webhook signature.',
            ]);
        } catch (\Exception $e) {
            Log::error('Razorpay webhook verification error: ' . $e->getMessage());
            throw ValidationException::withMessages([
                'webhook' => 'Webhook verification encountered an error.',
            ]);
        }
    }
}