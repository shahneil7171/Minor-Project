<?php

namespace App\Http\Controllers;

use App\Mail\PasswordChangedMail;
use App\Mail\PasswordResetOtpMail;
use App\Mail\RegistrationMail;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

class AuthController extends Controller
{
    private const OTP_EXPIRE_MINUTES = 10;

    public function showLoginForm()
    {
        return view('auth.login');
    }

    public function showRegisterForm()
    {
        return view('auth.register');
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8', 'confirmed', 'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/'],
            'account_type' => ['required', 'in:buyer,seller'],
        ], [
            'password.regex' => 'Password must contain at least one uppercase letter, one lowercase letter, one number, and one special character (@$!%*?&).',
            'account_type.in' => 'Please choose a valid account type.',
        ]);

        $user = User::create([
            'name' => $data['name'],
            // Normalise email casing so logins/password resets are case-insensitive.
            'email' => strtolower(trim($data['email'])),
            'password' => Hash::make($data['password']),
            // Only buyer/seller can be selected here; admin accounts are never
            // created through the public registration form.
            'account_type' => $data['account_type'],
        ]);

        Auth::login($user);

        // Set role in session based on stored account_type and start the
        // authenticated session (merging any guest cart along the way).
        $this->startSessionFor($request, $user);

        // Welcome email — sent only after registration fully succeeded. A mail
        // outage must never break registration, so transport failures are
        // logged and swallowed. The password is never included in the email.
        try {
            Mail::to($user->email)->send(new RegistrationMail($user));
        } catch (Throwable $e) {
            Log::error('Registration welcome email failed for user ' . $user->id . ': ' . $e->getMessage());
        }

        return redirect()->intended('/');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        // Normalise email casing to match stored accounts (created lowercased).
        $credentials['email'] = strtolower(trim($credentials['email']));

        // Admin accounts (e.g. the seeded admin@example.com) authenticate
        // through the exact same session flow as every customer — there is no
        // hard-coded backdoor that can mint an admin account on demand.
        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $user = Auth::user();

            // Blocked or deactivated accounts may not sign in.
            if (! $user->isActive()) {
                Auth::logout();

                return back()->withErrors([
                    'email' => $user->status === 'blocked'
                        ? 'Your account has been blocked. Please contact support.'
                        : 'Your account is currently inactive. Please contact support.',
                ])->onlyInput('email');
            }

            // Automatically set role from stored account_type and start the
            // authenticated session (merging any guest cart along the way).
            $this->startSessionFor($request, $user);

            return redirect()->intended('/');
        }

        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ])->onlyInput('email');
    }

    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }

    /**
     * Start an authenticated session for the user.
     *
     * Stores the role from account_type, merges any guest session cart into
     * the user's persistent database cart (so nothing is lost on sign-in),
     * then rotates the session id. Logout never deletes the database cart,
     * so it is still here when the customer signs back in.
     */
    private function startSessionFor(Request $request, User $user): void
    {
        $request->session()->put('role', $user->account_type);

        app(\App\Services\CartService::class)->mergeGuestCartIntoUserCart($user);

        $request->session()->regenerate();
    }

    public function showForgotPasswordForm()
    {
        return view('auth.forgot-password');
    }

    public function sendOtp(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $email = strtolower(trim($request->input('email')));
        $user = User::where('email', $email)->first();

        // Respond identically whether or not the account exists so this
        // endpoint cannot be used to enumerate registered email addresses.
        if ($user) {
            $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

            DB::table('password_reset_tokens')->updateOrInsert(
                ['email' => $email],
                ['token' => Hash::make($otp), 'created_at' => now()],
            );

            Mail::to($email)->send(new PasswordResetOtpMail($otp, self::OTP_EXPIRE_MINUTES));
        }

        return redirect()->route('password.verify')
            ->withInput(['email' => $email])
            ->with('status', 'If that email address is registered with us, a 6-digit verification code has been sent to it.');
    }

    public function showVerifyOtpForm()
    {
        return view('auth.verify-otp');
    }

    public function verifyOtp(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
            'otp' => ['required', 'digits:6'],
        ]);

        // Normalise the email casing exactly like sendOtp did so the lookup
        // cannot miss the stored token row (e.g. "Jane@Example.com").
        $email = strtolower(trim($request->input('email')));

        $record = DB::table('password_reset_tokens')
            ->where('email', $email)
            ->first();

        if (! $record || ! Hash::check($request->input('otp'), $record->token) || now()->diffInMinutes($record->created_at) >= self::OTP_EXPIRE_MINUTES) {
            return back()->withErrors(['otp' => 'The code is invalid or has expired.'])->onlyInput('email');
        }

        $request->session()->put('password_reset.email', $email);

        return redirect()->route('password.reset');
    }

    public function showResetForm()
    {
        if (! session()->has('password_reset.email')) {
            return redirect()->route('password.request')->withErrors(['email' => 'Please verify your email with the 6-digit code first.']);
        }

        return view('auth.reset-password', [
            'email' => session('password_reset.email'),
        ]);
    }

    public function resetPassword(Request $request)
    {
        // Same password policy as registration and change-password so every
        // entry point enforces the project's documented password rules.
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed', 'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/'],
        ], [
            'password.regex' => 'Password must contain at least one uppercase letter, one lowercase letter, one number, and one special character (@$!%*?&).',
        ]);

        $email = strtolower(trim($request->input('email')));

        if ($email !== session('password_reset.email')) {
            return back()->withErrors(['email' => 'The email does not match the verified address.']);
        }

        $user = User::where('email', $email)->first();

        if (! $user) {
            return back()->withErrors(['email' => 'No account was found for that email.']);
        }

        $user->forceFill([
            'password' => Hash::make($request->input('password')),
            'remember_token' => Str::random(60),
        ])->save();

        DB::table('password_reset_tokens')->where('email', $email)->delete();
        $request->session()->forget('password_reset.email');

        // Security notification for the completed reset (it complements the OTP
        // email sent when the reset was requested). The new password is never
        // included, and a mail failure must not affect the redirect.
        try {
            Mail::to($user->email)->send(new PasswordChangedMail($user, now()));
        } catch (Throwable $e) {
            Log::error('Password changed email failed for user ' . $user->id . ': ' . $e->getMessage());
        }

        return redirect()->route('login')->with('status', 'Your password has been updated successfully.');
    }
}
