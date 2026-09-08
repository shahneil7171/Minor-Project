<?php

namespace App\Http\Controllers;

use App\Models\SellerPaymentProfile;
use App\Notifications\StoreAlert;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Admin view of seller payment profiles.
 *
 * Administration use only: bank account numbers are ALWAYS rendered in
 * masked form (XXXXXX1234) even for admins; the full value is never
 * displayed, logged or emailed anywhere in the application.
 *
 * Verification is a manual, college-project flow: an admin can
 * Verify, Reject (with a rejection reason) or Request Update on a
 * profile. This never claims automated bank ownership verification or
 * real payment settlement — it only records the trusted human decision.
 */
class AdminSellerPaymentsController extends Controller
{
    /**
     * Every seller payment profile (newest first).
     */
    public function index(Request $request): View
    {
        $profiles = SellerPaymentProfile::query()
            ->with('seller')
            ->orderByDesc('id')
            ->paginate(15)
            ->appends($request->query());

        return view('admin.seller-payments', ['profiles' => $profiles]);
    }

    /**
     * Full admin detail view of one seller payment profile.
     */
    public function show(Request $request, SellerPaymentProfile $profile): View
    {
        $profile->load('seller');

        return view('admin.seller-payments-show', ['profile' => $profile]);
    }

    /**
     * Manually verify a seller's payment profile (admin decision, not
     * automated bank verification).
     */
    public function verify(Request $request, SellerPaymentProfile $profile): RedirectResponse
    {
        $data = $request->validate([
            'admin_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $profile->payment_status = 'verified';
        $profile->verified_at     = now();
        $profile->admin_note      = trim((string) ($data['admin_note'] ?? ''));
        $profile->rejection_reason = null;
        $profile->save();

        $this->notifySeller(
            $profile,
            'Payment details verified',
            'Your payment details have been verified by the store.'
        );

        return back()->with('success', 'Payment profile verified.');
    }

    /**
     * Reject a seller's payment profile — a rejection reason is
     * required so the seller knows what to fix. Nothing here claims the
     * bank details were fake; it records the admin's manual decision.
     */
    public function reject(Request $request, SellerPaymentProfile $profile): RedirectResponse
    {
        $data = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:1000'],
        ]);

        $profile->payment_status  = 'rejected';
        $profile->rejection_reason = trim($data['rejection_reason']);
        $profile->verified_at       = null;
        $profile->save();

        $this->notifySeller(
            $profile,
            'Payment details rejected',
            'Your payment details were rejected. Reason: ' . $profile->rejection_reason,
        );

        return back()->with('success', 'Payment profile rejected.');
    }

    /**
     * Ask a seller to update their payment details (soft block with a note).
     */
    public function requestUpdate(Request $request, SellerPaymentProfile $profile): RedirectResponse
    {
        $data = $request->validate([
            'admin_note' => ['required', 'string', 'max:1000'],
        ]);

        $profile->payment_status  = 'request_update';
        $profile->admin_note       = trim($data['admin_note']);
        $profile->rejection_reason = null;
        $profile->verified_at      = null;
        $profile->save();

        $this->notifySeller(
            $profile,
            'Payment details update requested',
            'The store has requested an update to your payment details. Note: ' . $profile->admin_note,
        );

        return back()->with('success', 'Update requested from the seller.');
    }

    /**
     * In-app alert to the owning seller — never includes bank credentials..
     */
    private function notifySeller(SellerPaymentProfile $profile, string $title, string $body): void
    {
        $seller = $profile->seller;

        if ($seller) {
            $seller->notify(new StoreAlert(
                $title,
                $body,
                route('seller.payment-settings.index'),
            ));
        }
    }
}
