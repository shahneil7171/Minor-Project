<?php

namespace App\Http\Controllers;

use App\Models\CodRefundPayout;
use App\Models\Refund;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Delivery-partner COD cash-refund payouts.
 *
 * A partner assigned a cash refund hands the money to the customer and records
 * the payout. A partner can NEVER simply click "Completed": the refund only
 * moves to completed once the required confirmation (a customer OTP or a
 * recorded receipt confirmation) is captured here. The confirmation actor,
 * timestamp and evidence are stored for an admin audit, and only the assigned
 * partner (or an admin) may confirm.
 */
class DeliveryPayoutController extends Controller
{
    /**
     * The partner's assigned cash-refund payouts.
     */
    public function index(Request $request)
    {
        $partner = $request->user();

        $payouts = CodRefundPayout::where('delivery_partner_id', $partner->id)
            ->with(['refund', 'returnRequest.order'])
            ->latest()
            ->paginate(15);

        return view('delivery.payouts.index', ['payouts' => $payouts]);
    }

    /**
     * One payout task. Only the assigned partner (or an admin) may open it.
     * Seller bank/IFSC or private payment-profile data is NEVER loaded here.
     */
    public function show(Request $request, CodRefundPayout $payout)
    {
        $this->authorizePayout($request, $payout);

        $payout->load(['refund', 'returnRequest.order', 'returnRequest.customer', 'deliveryPartner']);

        return view('delivery.payouts.show', ['payout' => $payout]);
    }

    /**
     * Mark the payout in progress (partner is carrying the cash out).
     */
    public function start(Request $request, CodRefundPayout $payout)
    {
        $this->authorizePayout($request, $payout);

        if ($payout->status === 'confirmed') {
            return back()->with('error', 'This payout has already been confirmed.');
        }

        $payout->forceFill(['status' => 'in_progress'])->save();

        return back()->with('success', 'Payout marked in progress.');
    }

    /**
     * Confirm the cash was handed to the customer.
     *
     * The refund is only marked completed when a confirmation value is present
     * (customer OTP or a recorded receipt confirmation). Without it the payout
     * stays in progress — a partner cannot complete a payout by clicking alone.
     */
    public function confirm(Request $request, CodRefundPayout $payout)
    {
        $this->authorizePayout($request, $payout);

        $data = $request->validate([
            'confirmation' => ['required', 'string', 'min:4', 'max:20'],
            'note'         => ['nullable', 'string', 'max:500'],
        ]);

        if ($payout->status === 'confirmed') {
            return back()->with('error', 'This payout has already been confirmed.');
        }

        DB::transaction(function () use ($payout, $data, $request): void {
            $locked = CodRefundPayout::whereKey($payout->id)->lockForUpdate()->first();
            $actor = $request->user();

            $locked->forceFill([
                'status'                => 'confirmed',
                'confirmation_evidence' => $data['confirmation'],
                'confirmed_at'          => now(),
                'confirmed_by'          => $actor->id,
                'note'                  => $data['note'] ?? $locked->note,
            ])->save();

            // Only NOW is the refund completed — after the payout confirmation.
            $refund = Refund::whereKey($locked->refund_id)->lockForUpdate()->first();
            if ($refund && $refund->status !== 'completed') {
                $refund->forceFill([
                    'status'              => 'completed',
                    'completed_at'        => now(),
                    'provider_reference'  => 'COD-PAYOUT-' . $locked->id,
                    'note'                => trim(($refund->note ? $refund->note . ' ' : '') . 'Cash payout confirmed by ' . $actor->name . '.'),
                ])->save();

                $return = $refund->returnRequest;
                if ($return) {
                    $return->forceFill([
                        'refund_status'   => 'refunded',
                        'refund_amount'   => $refund->amount,
                        'refund_reference' => $refund->provider_reference,
                        'refunded_at'     => now(),
                    ])->saveQuietly();
                }
            }
        });

        return back()->with('success', 'Cash payout confirmed. The refund is now completed.');
    }

    /**
     * A failed/disputed payout keeps the refund record intact for an auditable
     * retry — it never creates a second refund.
     */
    public function fail(Request $request, CodRefundPayout $payout)
    {
        $this->authorizePayout($request, $payout);

        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);
        $payout->forceFill(['status' => 'failed', 'note' => $data['reason'] ?? 'Payout failed.'])->save();

        $refund = $payout->refund;
        if ($refund && $refund->status !== 'completed') {
            $refund->forceFill(['status' => 'failed', 'note' => trim(($refund->note ? $refund->note . ' ' : '') . ($data['reason'] ?? 'Cash payout failed.'))])->save();
        }

        return back()->with('success', 'Payout marked failed. Admin can retry without creating a duplicate refund.');
    }

    private function authorizePayout(Request $request, CodRefundPayout $payout): void
    {
        $user = $request->user();

        $isAdmin = $user->isStaff();
        $isAssigned = (int) $payout->delivery_partner_id === (int) $user->id;

        abort_unless($isAdmin || $isAssigned, 403, 'You can only act on payouts assigned to you.');
    }
}
