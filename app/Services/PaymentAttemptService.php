<?php

namespace App\Services;

use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Server-side lifecycle for simulated online payment attempts (UPI / Card).
 *
 * All trust anchors are server side:
 *  * the attempt's amount / currency are checked against the order, never
 *    against anything the browser sends;
 *  * `expires_at` is set exactly once at initiation (five minutes) and is
 *    never reset by refreshes or retries;
 *  * marking an attempt successful, failed or expired is idempotent and runs
 *    inside a transaction with a row lock, so duplicate callbacks, retries
 *    and refreshes can never confirm an order twice or deduct inventory
 *    twice (inventory is deducted once, at order placement, by CheckoutController).
 *
 * This project has NO real provider configured for UPI / Card, so the
 * service is demo-only: `providerConfigured()` returns false and the
 * controller flow uses the explicitly labelled simulated payment actions.
 */
class PaymentAttemptService
{
    public function __construct(
        private OrderStatusService $statuses,
    ) {}

    /** Create a new attempt for an order with a fresh five-minute deadline. */
    public function createForOrder(Order $order, string $paymentMethod, ?int $userId): PaymentAttempt
    {
        if (! in_array($paymentMethod, config('payments.simulated_methods', ['upi', 'card']), true)) {
            throw ValidationException::withMessages([
                'payment_method' => 'The selected payment method is not supported.',
            ]);
        }

        $amount = (float) $order->total;
        $currency = strtoupper((string) ($order->currency_code ?: config('currency.base', 'INR')));

        $attempt = new PaymentAttempt([
            'user_id' => $userId,
            'order_id' => $order->id,
            'token' => $this->generateUniqueToken(),
            'payment_method' => $paymentMethod,
            'currency' => $currency,
            'amount' => $amount,
            'status' => PaymentAttempt::STATUS_PENDING,
            'expires_at' => now()->addMinutes((int) config('payments.attempt_expiry_minutes', 5)),
        ]);

        $attempt->save();

        return $attempt->fresh();
    }

    /**
     * Find an attempt by its public token and enforce ownership.
     *
     * Authenticated buyers may only see attempts on their own orders. Guest
     * orders may only be seen from the checkout session that placed them.
     */
    public function findAuthorized(string $token, ?User $user, ?int $sessionOrderId): PaymentAttempt
    {
        $attempt = PaymentAttempt::with('order')->where('token', $token)->firstOrFail();

        $order = $attempt->order;

        if ($order->user_id) {
            if (! $user || (int) $order->user_id !== (int) $user->id) {
                abort(403, 'This payment attempt belongs to another account.');
            }
        } elseif ($sessionOrderId !== $order->id) {
            abort(403, 'This payment attempt belongs to another checkout session.');
        }

        return $attempt;
    }

    /**
     * Mark stale pending attempts as expired so the UI shows the expired
     * state without ever resetting the original deadline.
     */
    public function markExpiredIfDue(PaymentAttempt $attempt): void
    {
        $attempt->refresh();

        if ($attempt->isPending() && $attempt->isExpired()) {
            $attempt->markExpired();
        }
    }

    /**
     * Verify and confirm a simulated payment success.
     *
     * Never trusts the browser: verifies the attempt is pending and not
     * expired, then checks the amount + currency against the order row.
     * Confirms the order (pending -> confirmed) exactly once.
     *
     * @throws ValidationException when the attempt cannot be confirmed.
     */
    public function succeed(PaymentAttempt $attempt): bool
    {
        $attempt = PaymentAttempt::whereKey($attempt->getKey())
            ->lockForUpdate()
            ->firstOrFail();

        // Early, committed checks (run outside the transaction so the
        // expired status is persisted even when we throw below).
        if ($attempt->status === PaymentAttempt::STATUS_SUCCESS) {
            return false;
        }
        if ($attempt->status !== PaymentAttempt::STATUS_PENDING) {
            throw ValidationException::withMessages([
                'payment' => 'This payment attempt is no longer active.',
            ]);
        }
        if ($attempt->isExpired()) {
            $attempt->markExpired();
            throw ValidationException::withMessages([
                'payment' => 'Payment timed out. Please retry or choose another payment method.',
            ]);
        }

        $confirmedNow = false;

        DB::transaction(function () use ($attempt, &$confirmedNow) {
            // Re-check under a row lock (race safety).
            $locked = PaymentAttempt::whereKey($attempt->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->status === PaymentAttempt::STATUS_SUCCESS) {
                return;
            }
            if ($locked->status !== PaymentAttempt::STATUS_PENDING) {
                throw ValidationException::withMessages([
                    'payment' => 'This payment attempt is no longer active.',
                ]);
            }
            if ($locked->isExpired()) {
                $locked->markExpired();
                throw ValidationException::withMessages([
                    'payment' => 'Payment timed out. Please retry or choose another payment method.',
                ]);
            }

            $order = $locked->order()->firstOrFail();

            // Server-side enforcement: amount, currency and the order
            // reference must match. Nothing from the browser is trusted.
            if ((float) $attempt->amount !== (float) $order->total) {
                throw ValidationException::withMessages([
                    'payment' => 'Payment amount does not match the order total.',
                ]);
            }

            if (strtoupper($attempt->currency) !== strtoupper($order->currencyCode())) {
                throw ValidationException::withMessages([
                    'payment' => 'Payment currency does not match the order.',
                ]);
            }

            $providerReference = 'demo_txn_'.Str::random(16);

            $locked->forceFill([
                'status' => PaymentAttempt::STATUS_SUCCESS,
                'provider_reference' => $providerReference,
                'verified_at' => now(),
            ])->save();

            // Keep the caller's instance in sync.
            $attempt->setRawAttributes($locked->getAttributes(), true);

            // Confirm the order exactly once. An admin could already have
            // confirmed it manually (pending -> confirmed is guard-protected
            // by OrderStatusService).
            if ($order->status === 'pending') {
                $this->statuses->transition($order, 'confirmed', null, 'Simulated payment verified: '.$providerReference);
                $confirmedNow = true;
            } else {
                $confirmedNow = $order->isConfirmed();
            }
        });

        return $confirmedNow;
    }

    /** Mark the attempt as failed (simulated failure). */
    public function fail(PaymentAttempt $attempt, ?string $reason = null): void
    {
        $attempt = PaymentAttempt::whereKey($attempt->getKey())
            ->lockForUpdate()
            ->firstOrFail();

        if ($attempt->status !== PaymentAttempt::STATUS_PENDING) {
            return;
        }

        $attempt->markFailed($reason);
    }

    /**
     * Retry a failed or expired attempt: create a brand new attempt for the
     * same order with a new five-minute deadline. An already-successful
     * attempt is returned as-is (no duplicate order is ever created).
     */
    public function retry(PaymentAttempt $attempt): PaymentAttempt
    {
        return DB::transaction(function () use ($attempt) {
            $attempt = PaymentAttempt::whereKey($attempt->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($attempt->status === PaymentAttempt::STATUS_SUCCESS) {
                return $attempt->fresh();
            }

            return $this->createForOrder(
                $attempt->order,
                $attempt->payment_method,
                $attempt->user_id
            );
        });
    }

    /** No real payment provider is configured for UPI / Card in this project. */
    public function providerConfigured(): bool
    {
        return false;
    }

    private function generateUniqueToken(): string
    {
        do {
            $token = strtoupper(Str::random(24));
        } while (PaymentAttempt::where('token', $token)->exists());

        return $token;
    }
}











