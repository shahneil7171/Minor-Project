<?php

namespace App\Http\Controllers;

use App\Models\BuyerPaymentMethod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Buyer Saved Payment Methods.
 *
 * Buyer-only area (route group carries the "auth" middleware). A buyer can
 * manage only their OWN saved payment methods. Ownership is enforced on
 * every read, update and delete operation.
 *
 * Security notes:
 * - UPI IDs are stored as-is (validated on save) but are NOT payment
 *   authorization.
 * - Raw card numbers and CVVs are never accepted or stored. Only safe
 *   display metadata (brand, masked last four, expiry) and a gateway
 *   token/reference may be stored. The configured gateways do not currently
 *   expose card tokenization, so card storage is limited to metadata.
 * - Net banking passwords, PINs and OTPs are never stored.
 */
class BuyerPaymentMethodController extends Controller
{
    /**
     * List the authenticated buyer's saved payment methods.
     */
    public function index(Request $request): View
    {
        $methods = BuyerPaymentMethod::where('user_id', $request->user()->id)
            ->orderByDesc('is_default')
            ->orderByDesc('created_at')
            ->get();

        return view('profile.payment-methods', [
            'methods' => $methods,
        ]);
    }

    /**
     * Save a new payment method for the authenticated buyer.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'type' => ['required', 'in:upi,card,netbanking'],
            'label' => ['nullable', 'string', 'max:50'],
            'upi_id' => ['required_if:type,upi', 'nullable', 'string', 'max:100', 'regex:/^[\w.\-]{2,64}@[a-zA-Z]{2,32}$/'],
            // Card fields: only safe metadata, never raw PAN/CVV.
            'card_brand' => ['required_if:type,card', 'nullable', 'string', 'max:50'],
            'card_last4' => ['required_if:type,card', 'nullable', 'string', 'size:4', 'regex:/^[0-9]{4}$/'],
            'card_expiry_month' => ['required_if:type,card', 'nullable', 'integer', 'between:1,12'],
            'card_expiry_year' => ['required_if:type,card', 'nullable', 'integer', 'min:2024', 'max:2040'],
            'bank_code' => ['required_if:type,netbanking', 'nullable', 'string', 'max:50'],
            'is_default' => ['boolean'],
        ]);

        // Net banking: only allow if the gateway supports it. Currently the
        // configured gateways (Razorpay/Cashfree) do not expose a saved-bank
        // flow, so we accept the preference but note it is informational.
        $method = BuyerPaymentMethod::create([
            'user_id' => $user->id,
            'type' => $data['type'],
            'label' => $data['label'] ?? null,
            'upi_id' => $data['upi_id'] ?? null,
            'card_brand' => $data['card_brand'] ?? null,
            'card_last4' => $data['card_last4'] ?? null,
            'card_expiry_month' => $data['card_expiry_month'] ?? null,
            'card_expiry_year' => $data['card_expiry_year'] ?? null,
            'bank_code' => $data['bank_code'] ?? null,
            'is_default' => (bool) ($data['is_default'] ?? false),
        ]);

        // If this is set as default, unset any other default for this user.
        if ($method->is_default) {
            BuyerPaymentMethod::where('user_id', $user->id)
                ->where('id', '!=', $method->id)
                ->update(['is_default' => false]);
        }

        // If this is the user's first method, make it default automatically.
        $count = BuyerPaymentMethod::where('user_id', $user->id)->count();
        if ($count === 1) {
            $method->update(['is_default' => true]);
        }

        return redirect()->route('profile.payment-methods.index')
            ->with('success', 'Payment method saved.');
    }

    /**
     * Set a saved payment method as the default.
     */
    public function setDefault(Request $request, BuyerPaymentMethod $method): RedirectResponse
    {
        $this->authorizeOwner($request, $method);

        BuyerPaymentMethod::where('user_id', $request->user()->id)
            ->update(['is_default' => false]);

        $method->update(['is_default' => true]);

        return redirect()->route('profile.payment-methods.index')
            ->with('success', 'Default payment method updated.');
    }

    /**
     * Remove a saved payment method.
     */
    public function destroy(Request $request, BuyerPaymentMethod $method): RedirectResponse
    {
        $this->authorizeOwner($request, $method);

        $wasDefault = $method->is_default;
        $method->delete();

        // If we deleted the default, promote the most recent remaining method.
        if ($wasDefault) {
            $next = BuyerPaymentMethod::where('user_id', $request->user()->id)
                ->orderByDesc('created_at')
                ->first();

            if ($next) {
                $next->update(['is_default' => true]);
            }
        }

        return redirect()->route('profile.payment-methods.index')
            ->with('success', 'Payment method removed.');
    }

    /**
     * Ensure the authenticated buyer owns the given payment method.
     */
    private function authorizeOwner(Request $request, BuyerPaymentMethod $method): void
    {
        abort_unless(
            $request->user() && $request->user()->id === $method->user_id,
            403
        );
    }
}
