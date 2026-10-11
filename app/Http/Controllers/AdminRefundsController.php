<?php

namespace App\Http\Controllers;

use App\Models\CodRefundPayout;
use App\Models\Refund;
use App\Models\Replacement;
use App\Models\ReturnRequest;
use App\Models\User;
use App\Services\RefundService;
use App\Services\ReplacementService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Admin refund / replacement orchestration.
 *
 * This controller is the ONLY place an admin initiates or settles money for a
 * return. It never invents an amount: RefundService recomputes every refund
 * from the recorded order data. Each action is delegated to the owning service
 * so the wallet ledger, refund record, return status and notifications can
 * never drift apart, and every write is idempotent.
 *
 * Separation of concerns:
 *  - refunds        -> RefundService (original/coins/COD)
 *  - replacements   -> ReplacementService (exchanges)
 *  - COD payouts    -> confirmed later by the assigned delivery partner
 */
class AdminRefundsController extends Controller
{
    public function __construct(
        private RefundService $refunds,
        private ReplacementService $replacements,
    ) {
    }

    private function authorizeAdmin(): void
    {
        abort_unless(auth()->check() && auth()->user()->isStaff(), 403);
    }

    /**
     * Initiate a refund for an approved/inspected return, choosing the method
     * (original simulated payment / Smart Coins / COD cash). Idempotent: the
     * service returns the existing refund if one already exists for the return.
     */
    public function initiate(Request $request, ReturnRequest $return)
    {
        $this->authorizeAdmin();

        $data = $request->validate([
            'method' => ['required', Rule::in(Refund::METHODS)],
        ]);

        $refund = $this->refunds->initiate($return, $request->user(), $data['method']);

        $message = match ($data['method']) {
            'smart_coins' => 'Smart Coins refund initiated. Credit the wallet to complete it.',
            'cod_cash'    => 'COD cash refund task created. Assign a delivery partner to pay it out.',
            default       => 'Refund to the original (simulated) payment method initiated.',
        };

        return back()->with('success', 'Refund #' . $refund->id . ' initiated. ' . $message);
    }

    /**
     * Settle a refund. Smart Coins credits the wallet atomically; the
     * simulated online method records completion WITHOUT claiming a real bank
     * transfer; COD is settled separately after partner confirmation.
     */
    public function settle(Request $request, Refund $refund)
    {
        $this->authorizeAdmin();

        try {
            if ($refund->method === 'smart_coins') {
                $this->refunds->processSmartCoins($refund, $request->user());
                $message = 'Smart Coins credited to the customer wallet.';
            } elseif ($refund->method === 'original_simulated') {
                $this->refunds->processOnlineRefund($refund, $request->user(), $request->input('provider_reference'));
                $message = 'Refund recorded as completed (SIMULATED — no real bank transfer).';
            } else {
                return back()->with('error', 'COD cash refunds are completed by the assigned delivery partner after payout confirmation.');
            }
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('success', $message);
    }


    /**
     * Mark a refund failed but keep the record for an auditable retry.
     */
    public function fail(Request $request, Refund $refund)
    {
        $this->authorizeAdmin();

        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);
        $this->refunds->markFailed($refund, $request->user(), $data['reason'] ?? null);

        return back()->with('success', 'Refund marked failed. It can be retried without creating a duplicate.');
    }

    /**
     * Assign a delivery partner (refund agent) to a COD cash refund payout.
     */
    public function assignPayout(Request $request, Refund $refund)
    {
        $this->authorizeAdmin();

        $data = $request->validate([
            'delivery_partner_id' => ['required', 'integer'],
        ]);

        $partner = User::where('account_type', 'delivery_partner')->find($data['delivery_partner_id']);

        if (! $partner) {
            return back()->with('error', 'The selected user is not a delivery partner.');
        }

        $payout = CodRefundPayout::firstOrCreate(
            ['refund_id' => $refund->id],
            ['return_request_id' => $refund->return_request_id, 'amount' => $refund->amount, 'status' => 'assigned', 'assigned_by' => $request->user()->id]
        );

        $payout->forceFill(['delivery_partner_id' => $partner->id, 'status' => 'assigned'])->save();

        $partner->notify(new \App\Notifications\StoreAlert(
            'COD cash refund assigned',
            'A cash refund of Rs ' . number_format((float) $refund->amount, 2) . ' was assigned to you.',
            route('delivery.payouts.index')
        ));

        return back()->with('success', 'Cash refund payout assigned to ' . $partner->name . '.');
    }

    /**
     * Authorize a replacement for a return (admin). Stock availability is
     * verified by the service before approval.
     */
    public function approveReplacement(Request $request, ReturnRequest $return)
    {
        $this->authorizeAdmin();

        $replacement = Replacement::where('return_request_id', $return->id)->first();

        if (! $replacement) {
            return back()->with('error', 'No replacement has been requested for this return yet.');
        }

        try {
            $this->replacements->approve($replacement, $request->user());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('success', 'Replacement approved.');
    }
}
