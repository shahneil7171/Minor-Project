<?php

namespace App\Http\Controllers;

use App\Mail\OrderConfirmationMail;
use App\Models\Order;
use App\Models\User;
use App\Services\CartService;
use App\Models\PaymentAttempt;
use App\Services\PaymentAttemptService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

/**
 * Payment-attempt endpoints for the simulated online methods (UPI / Card).
 *
 * Every endpoint enforces payment ownership: one user cannot inspect or
 * modify another user's attempt. All verification is server side — the
 * browser's countdown is display-only, and the expiry deadline (exactly five
 * minutes from initiation, stored on the attempt) is enforced on every
 * mutating request.
 *
 * No real provider is configured for UPI / Card in this project, so the
 * success path is an explicitly labelled simulated payment. The service
 * layer guards the amount, currency and order reference regardless of mode.
 */
class PaymentAttemptController extends Controller
{
    /** Show the payment page for an attempt (pending / success / failed / expired). */
    public function show(string $token, Request $request): \Illuminate\View\View
    {
        $attempt = app(PaymentAttemptService::class)
            ->findAuthorized($token, $request->user(), session('checkout_order_id'));

        // Lazily mark the attempt expired so the deadline is enforced
        // server side without ever resetting the original expires_at.
        app(PaymentAttemptService::class)->markExpiredIfDue($attempt);
        $attempt->refresh();

        $order = $attempt->order()->with('items')->firstOrFail();

        // Manual UPI (seller QR) is never auto-confirmed: only the simulated
        // card flow has a server-side demo confirmation step.
        $isSimulatedCard = $attempt->payment_method === 'card';

        return view('payments.attempt', [
            'attempt' => $attempt,
            'order' => $order,
            'expiresAt' => $attempt->expires_at?->toISOString(),
            'remainingSeconds' => max(0, (int) now()->diffInSeconds($attempt->expires_at, false)),
            'demoMode' => config('payments.demo_mode', true),
            'isSimulatedCard' => $isSimulatedCard,
            'sellerPayments' => session('checkout_seller_payments', []),
        ]);
    }

    /** Simulated payment success (demo mode): verifies server-side, then confirms the order. */
    public function pay(string $token, Request $request): \Illuminate\Http\RedirectResponse
    {
        $attempt = app(PaymentAttemptService::class)
            ->findAuthorized($token, $request->user(), session('checkout_order_id'));

        // Lazily mark expired so the deadline is enforced even on pay attempts.
        app(PaymentAttemptService::class)->markExpiredIfDue($attempt);
        $attempt->refresh();

        // Manual UPI is never auto-confirmed by a demo button: only the
        // simulated card flow has a server-side demo confirmation step.
        if ($attempt->payment_method !== 'card') {
            return redirect()->route('payment.attempt.show', ['token' => $token])
                ->with('error', 'Manual UPI payments require verification and cannot be simulated. Please complete the transfer and wait for verification.');
        }

        $confirmedNow = false;

        try {
            $confirmedNow = app(PaymentAttemptService::class)->succeed($attempt);
        } catch (ValidationException $e) {
            Log::warning('Simulated payment could not be confirmed', [
                'token' => $token,
                'errors' => $e->errors(),
            ]);

            return redirect()->route('payment.attempt.show', ['token' => $token])
                ->with('error', collect($e->errors())->flatten()->first() ?? 'Payment could not be verified. Please try again.');
        }

        $order = $attempt->order()->with('items')->firstOrFail();

        // Clear the cart only now that payment is verified — a failed or
        // expired attempt leaves the cart intact so the buyer can retry.
        app(CartService::class)->clear();
        session()->forget('buy_now_item');
        session(['checkout_order_id' => $order->id]);

        // Only send the confirmation email once per confirmed order.
        if ($confirmedNow) {
            try {
                Mail::to($order->user?->email ?? $order->customer_email)
                    ->send(new OrderConfirmationMail($order->load('items')));
            } catch (\Throwable $e) {
                Log::error('Order confirmation email failed for order '.$order->order_number.': '.$e->getMessage());
            }
        }

        return redirect()->route('checkout.complete')
            ->with('success', 'Payment verified. Order #'.$order->order_number.' has been confirmed.');
    }

    /** Simulated payment failure (demo mode). */
    public function fail(string $token, Request $request): \Illuminate\Http\RedirectResponse
    {
        $attempt = app(PaymentAttemptService::class)
            ->findAuthorized($token, $request->user(), session('checkout_order_id'));

        app(PaymentAttemptService::class)->fail(
            $attempt,
            'Simulated payment declined (demo mode).'
        );

        return redirect()->route('payment.attempt.show', ['token' => $token])
            ->with('error', 'Payment failed. Please retry or choose another payment method.');
    }

    /** Retry: create a new attempt with a fresh five-minute deadline. */
    public function retry(string $token, Request $request): \Illuminate\Http\RedirectResponse
    {
        $attempt = app(PaymentAttemptService::class)
            ->findAuthorized($token, $request->user(), session('checkout_order_id'));

        $attempt = app(PaymentAttemptService::class)->retry($attempt);

        if ($attempt->status === 'success') {
            return redirect()->route('checkout.complete')
                ->with('success', 'This order was already paid and confirmed.');
        }

        return redirect()->route('payment.attempt.show', ['token' => $attempt->token])
            ->with('success', 'A new payment attempt has been started. It expires in five minutes.');
    }
}
