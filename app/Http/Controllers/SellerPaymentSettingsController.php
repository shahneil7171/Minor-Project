<?php

namespace App\Http\Controllers;

use App\Models\SellerPaymentProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Seller Payment Settings (UPI / QR / bank details).
 *
 * Seller-only area (route group carries the "seller" middleware):
 * a seller can configure exactly ONE payment profile — their own. The
 * profile is always looked up by the authenticated user's id, never by
 * client input, so one seller can never read or overwrite another
 * seller's payment details.
 *
 * Security notes:
 * - The bank account number is stored encrypted (model cast) and only
 *   ever displayed in masked form (XXXXXX1234) on this page.
 * - QR codes are stored under public/uploads/qr-codes with
 *   server-generated filenames; no client-supplied path is ever trusted.
 * - When replacing a QR code, the old file is deleted only AFTER the new
 *   upload has been stored successfully.
 */
class SellerPaymentSettingsController extends Controller
{
    private const QR_UPLOAD_DIR = 'uploads/qr-codes';

    /**
     * Show the seller's payment settings page.
     */
    public function index(Request $request): View
    {
        $profile = SellerPaymentProfile::firstOrNew(['seller_id' => $request->user()->id]);

        return view('seller.payment-settings', [
            'profile'   => $profile,
            // Masked form only — the full number is never sent to the view.
            'maskedAccountNumber' => $profile->maskedAccountNumber(),
        ]);
    }

    /**
     * Validate and save the seller's payment details.
     */
    public function update(Request $request): RedirectResponse
    {
        // When the seller enters a NEW account number, they must confirm it.
        // Blank confirm + blank account = fine; blank confirm + filled account
        // = error (catches typos server-side, never only in the browser).
        $enteringNewAccount = trim((string) ($request->input('account_number') ?? '')) !== '';

        $data = $request->validate([
            // UPI ID: local handle + @ + PSP handle (e.g. seller@upi).
            'upi_id'              => ['nullable', 'string', 'max:100', 'regex:/^[\w.\-]{2,64}@[a-zA-Z]{2,32}$/'],

            // Payment contact number (shown to buyers at checkout for the
            // UPI flow — it is intentionally distinct from the account
            // holder's private profile phone).
            'mobile_number'       => ['nullable', 'string', 'max:12', 'regex:/^[0-9]{10,12}$/'],

            // QR image: strict image whitelist + size limit; executables
            // and non-image content are rejected before anything is stored.
            'qr_code'             => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],

            // Optional bank details.
            'account_holder_name' => ['nullable', 'string', 'max:100'],
            'bank_name'           => ['nullable', 'string', 'max:100'],
            'branch_name'          => ['nullable', 'string', 'max:100'],
            'account_number'      => ['nullable', 'string', 'max:20', 'regex:/^[0-9]{6,20}$/'],
            'confirm_account_number' => $enteringNewAccount
                ? ['required', 'same:account_number']
                : ['nullable'],
            'ifsc_code'           => ['nullable', 'string', 'max:11', 'regex:/^[A-Za-z]{4}0[A-Za-z0-9]{6}$/'],
            'account_type'        => ['nullable', 'in:savings,current'],

            // Payment contact email — explicitly entered by the seller; it is
            // NOT auto-derived from the login email.
            'payment_email'       => ['nullable', 'email', 'max:190'],

            // Preferred payout method (UPI / bank transfer / both).
            'payment_method'      => ['nullable', 'in:upi,bank_transfer,both'],

            'is_active'           => ['nullable', 'boolean'],
        ]);

        $profile = SellerPaymentProfile::firstOrNew(['seller_id' => $request->user()->id]);

        $profile->upi_id       = $data['upi_id'] ?? null;
        $profile->mobile_number = $data['mobile_number'] ?? null;
        $profile->payment_email = $data['payment_email'] ?? null;
        $profile->account_holder_name = $data['account_holder_name'] ?? null;
        $profile->bank_name    = $data['bank_name'] ?? null;
        $profile->branch_name  = $data['branch_name'] ?? null;
        $profile->ifsc_code    = isset($data['ifsc_code']) ? strtoupper($data['ifsc_code']) : null;
        $profile->account_type = $data['account_type'] ?? null;
        $profile->payment_method = $data['payment_method'] ?? null;

        // Blank account number keeps the stored one (never blanked by
        // accident); a non-blank value re-encrypts through the model cast.
        $newAccountNumber = trim((string) ($data['account_number'] ?? ''));
        $profile->account_number = $newAccountNumber !== ''
            ? $newAccountNumber
            : $profile->account_number;

        $profile->is_active = $request->boolean('is_active');

        // Payment status lifecycle (college-project demonstration — storing
        // these details never claims real payment settlement). An admin-marked
        // "verified" profile stays verified when the seller merely refreshes it;
        // any other state (configured / pending / rejected / request_update)
        // becomes "configured" again so admins re-review the latest details..
        $profile->payment_status = $profile->hasAnyPaymentInfo()
            ? ($profile->isVerified() ? 'verified' : 'configured')
            : 'not_configured';

        // QR upload: store the new file first, then drop the previous one —
        // a failed upload can never leave the seller without their QR.
        if ($request->hasFile('qr_code')) {
            $file = $request->file('qr_code');

            $uploadDir = public_path(self::QR_UPLOAD_DIR);
            if (! is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            // Server-generated filename — never trust client paths.
            $extension = strtolower($file->guessExtension() ?: 'png');
            $filename = time() . '-' . mt_rand(1000, 9999) . '-seller' . $request->user()->id . '.' . $extension;

            $file->move($uploadDir, $filename);

            $oldPath = $profile->qr_code_path;
            $profile->qr_code_path = '/' . self::QR_UPLOAD_DIR . '/' . $filename;

            // New image is safely stored — now remove the superseded file.
            if ($oldPath && $oldPath !== $profile->qr_code_path) {
                $this->deleteQrFile($oldPath);
            }
        }

        $profile->seller_id = $request->user()->id;
        $profile->save();

        return back()->with('success', 'Payment details saved.');
    }

    /**
     * Remove the seller's QR code (file + reference).
     */
    public function removeQr(Request $request): RedirectResponse
    {
        $profile = SellerPaymentProfile::where('seller_id', $request->user()->id)->first();

        if ($profile && $profile->qr_code_path) {
            $this->deleteQrFile($profile->qr_code_path);
            $profile->qr_code_path = null;
            $profile->save();
        }

        return back()->with('success', 'QR code removed.');
    }

    /**
     * Delete a stored QR file. Paths always come from our own database
     * records (never user input) and are constrained to the QR directory.
     */
    private function deleteQrFile(?string $relativePath): void
    {
        if (! $relativePath) {
            return;
        }

        $base = realpath(public_path(self::QR_UPLOAD_DIR));
        $target = realpath(public_path(ltrim($relativePath, '/')));

        // realpath() containment check: refuse anything resolving outside
        // the QR upload directory.
        if ($target && $base && str_starts_with($target, $base . DIRECTORY_SEPARATOR) && is_file($target)) {
            @unlink($target);
        }
    }
}

