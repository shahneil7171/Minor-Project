<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

interface PaymentGateway
{
    /**
     * Short machine name for the provider (used for routing and display).
     */
    public function name(): string;

    /**
     * Provider operation mode: 'test' or 'live'.
     */
    public function mode(): string;

    /**
     * Create a provider session/order server-side for a pending order.
     *
     * @return array{
     *     id: string,
     *     amount: int,
     *     currency: string,
     *     details: array<string, mixed>,
     *     type: 'script'|'redirect',
     *     theme?: array{ color: string }
     * }
     */
    public function createSession(Order $order): array;

    /**
     * Verify a provider payment result on return from the provider.
     *
     * The browser is never trusted. The provider is (signature + confirmed
     * status). On success the order is transitioned to captured and returned.
     *
     * @return array{payment_id: string, status: string}
     *
     * @throws ValidationException when the result cannot be verified.
     */
    public function verify(Request $request, Order $order): array;

    /**
     * Validate an incoming provider webhook and return the parsed event.
     *
     * @return array{event_type: string, payload: array<string, mixed>}
     *
     * @throws ValidationException when the webhook signature is invalid.
     */
    public function handleWebhook(Request $request, Order $order): array;

    /**
     * Map a provider event to the local resource status the order should move
     * to. Used by the webhook dispatcher.
     */
    public function statusForWebhook(string $eventType): ?string;

    /**
     * Initiate a refund with the provider for a fully/partially captured order.
     *
     * @return array{id: string, amount: int, currency: string, status: string}
     *
     * @throws ValidationException when the refund request fails.
     */
    public function initiateRefund(Order $order, float $amount, string $idempotencyKey): array;

    /**
     * Fetch a provider refund to check its status.
     *
     * Cashfree's refund fetch API requires the order context (its
     * `cashfree_order_id`) alongside the refund id, so the Order is always
     * passed through even though Razorpay only needs the refund id itself.
     *
     * @return array<string, mixed>
     *
     * @throws ValidationException when the refund cannot be fetched.
     */
    public function fetchRefundStatus(Order $order, string $providerRefundId): array;
}
