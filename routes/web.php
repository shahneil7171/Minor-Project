<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (Auth::check()) {
        return view('dashboard');
    }

    return view('splash');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.post');
    Route::get('/forgot-password', [AuthController::class, 'showForgotPasswordForm'])->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'sendOtp'])->name('password.email');
    Route::get('/forgot-password/verify', [AuthController::class, 'showVerifyOtpForm'])->name('password.verify');
    Route::post('/forgot-password/verify', [AuthController::class, 'verifyOtp'])->name('password.verify.post');
    Route::get('/reset-password', [AuthController::class, 'showResetForm'])->name('password.reset');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('password.update');
    Route::get('/register', [AuthController::class, 'showRegisterForm'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->name('register.post');
});

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');

    Route::get('/home', function () {
        return redirect()->route('dashboard');
    })->name('home');

    Route::get('/products', function () {
        return view('products');
    })->name('products');

    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
});
