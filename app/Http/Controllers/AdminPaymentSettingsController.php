<?php

namespace App\Http\Controllers;

use App\Models\AdminPaymentProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminPaymentSettingsController extends Controller
{
    private const QR_UPLOAD_DIR = 'uploads/qr-codes';

    public function index(Request $request): View
    {
        $this->authorizeAdmin();
        $profile = AdminPaymentProfile::forAdmin();
        return view('admin.payment-settings', [
            'profile' => $profile,
            'maskedAccountNumber' => $profile->maskedAccountNumber(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->authorizeAdmin();
        $enteringNewAccount = trim((string) ($request->input('account_number') ?? '')) !== '';
        $data = $request->validate([
            'upi_id' => ['nullable', 'string', 'max:100', 'regex:/^[\w.\-]{2,64}@[a-zA-Z]{2,32}$/'],
            'mobile_number' => ['nullable', 'string', 'max:12', 'regex:/^[0-9]{10,12}$/'],
            'qr_code' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'account_holder_name' => ['nullable', 'string', 'max:100'],
            'bank_name' => ['nullable', 'string', 'max:100'],
            'branch_name' => ['nullable', 'string', 'max:100'],
            'account_number' => ['nullable', 'string', 'max:20', 'regex:/^[0-9]{6,20}$/'],
            'confirm_account_number' => $enteringNewAccount
                ? ['required', 'same:account_number']
                : ['nullable'],
            'ifsc_code' => ['nullable', 'string', 'max:11', 'regex:/^[A-Za-z]{4}0[A-Za-z0-9]{6}$/'],
            'account_type' => ['nullable', 'in:savings,current'],
            'payment_method' => ['nullable', 'in:upi,bank_transfer,both'],
            'payment_email' => ['nullable', 'email', 'max:255'],
            'is_active' => ['boolean'],
            'remove_qr' => ['boolean'],
        ]);
        $profile = AdminPaymentProfile::forAdmin();
        $profile->upi_id = trim((string) ($data['upi_id'] ?? ''));
        $profile->mobile_number = trim((string) ($data['mobile_number'] ?? ''));
        $profile->payment_email = trim((string) ($data['payment_email'] ?? ''));
        $profile->payment_method = $data['payment_method'] ?? null;
        $profile->account_holder_name = trim((string) ($data['account_holder_name'] ?? ''));
        $profile->bank_name = trim((string) ($data['bank_name'] ?? ''));
        $profile->branch_name = trim((string) ($data['branch_name'] ?? ''));
        $profile->ifsc_code = trim((string) ($data['ifsc_code'] ?? ''));
        $profile->account_type = $data['account_type'] ?? null;
        $profile->is_active = $request->boolean('is_active');
        $profile->payment_status = $profile->hasAnyPaymentInfo()
            ? ($profile->isVerified() ? 'verified' : 'configured')
            : 'not_configured';
        if ($request->boolean('remove_qr')) {
            if ($profile->qr_code_path) {
                $this->deleteQrFile($profile->qr_code_path);
                $profile->qr_code_path = null;
            }
        }
        if ($request->hasFile('qr_code')) {
            $file = $request->file('qr_code');
            $uploadDir = public_path(self::QR_UPLOAD_DIR);
            if (! is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            $extension = strtolower($file->guessExtension() ?: 'png');
            $filename = time() . '-' . mt_rand(1000, 9999) . '-admin.' . $extension;
            $file->move($uploadDir, $filename);
            $oldPath = $profile->qr_code_path;
            $profile->qr_code_path = '/' . self::QR_UPLOAD_DIR . '/' . $filename;
            if ($oldPath && $oldPath !== $profile->qr_code_path) {
                $this->deleteQrFile($oldPath);
            }
        }
        $profile->id = 1;
        $profile->save();
        return back()->with('success', 'Payment details saved.');
    }

    public function removeQr(Request $request): RedirectResponse
    {
        $this->authorizeAdmin();
        $profile = AdminPaymentProfile::forAdmin();
        if ($profile->qr_code_path) {
            $this->deleteQrFile($profile->qr_code_path);
            $profile->qr_code_path = null;
            $profile->id = 1;
            $profile->save();
        }
        return back()->with('success', 'QR code removed.');
    }

    private function authorizeAdmin(): void
    {
        abort_unless(auth()->check() && auth()->user()->isStaff(), 403);
    }

    private function deleteQrFile(?string $relativePath): void
    {
        if (! $relativePath) {
            return;
        }
        $base = realpath(public_path(self::QR_UPLOAD_DIR));
        $target = realpath(public_path(ltrim($relativePath, '/')));
        if ($target && $base && str_starts_with($target, $base . DIRECTORY_SEPARATOR) && is_file($target)) {
            @unlink($target);
        }
    }
}
