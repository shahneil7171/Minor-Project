<?php

namespace App\Http\Controllers;

use App\Mail\PasswordResetOtpMail;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

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
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        Auth::login($user);

        // If role was provided during registration, persist it in session
        if ($request->filled('role') && in_array($request->input('role'), ['buyer', 'seller', 'admin'])) {
            $request->session()->put('role', $request->input('role'));
        }

        $request->session()->regenerate();

        return redirect()->intended('/');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        // Special-case admin static credentials when no explicit role selected
        $selectedRole = $request->input('role');
        if (empty($selectedRole) && $credentials['email'] === 'admin@example.com' && $credentials['password'] === 'admin123') {
            $user = User::firstOrCreate(
                ['email' => 'admin@example.com'],
                ['name' => 'Admin', 'password' => Hash::make('admin123')]
            );

            Auth::login($user);
            $request->session()->regenerate();
            $request->session()->put('role', 'admin');

            return redirect()->intended('/');
        }

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            // Persist chosen role if provided, otherwise default to buyer
            if ($request->filled('role') && in_array($request->input('role'), ['buyer', 'seller', 'admin'])) {
                $request->session()->put('role', $request->input('role'));
            } elseif (! $request->filled('role')) {
                $request->session()->put('role', 'buyer');
            }

            $request->session()->regenerate();

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

        if (! $user) {
            return back()
                ->withErrors(['email' => 'We could not find an account with that email address.'])
                ->onlyInput('email');
        }

        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $email],
            ['token' => Hash::make($otp), 'created_at' => now()],
        );

        Mail::to($email)->send(new PasswordResetOtpMail($otp, self::OTP_EXPIRE_MINUTES));

        return redirect()->route('password.verify')
            ->withInput(['email' => $email])
            ->with('status', 'We sent a 6-digit verification code to your email address.');
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

        $record = DB::table('password_reset_tokens')
            ->where('email', $request->input('email'))
            ->first();

        if (! $record || ! Hash::check($request->input('otp'), $record->token) || now()->diffInMinutes($record->created_at) >= self::OTP_EXPIRE_MINUTES) {
            return back()->withErrors(['otp' => 'The code is invalid or has expired.'])->onlyInput('email');
        }

        $request->session()->put('password_reset.email', $request->input('email'));

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
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $email = $request->input('email');

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

        return redirect()->route('login')->with('status', 'Your password has been updated successfully.');
    }
}
