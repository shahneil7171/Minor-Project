<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // PHASE 3 — resolve the visitor's language + currency on every web
        // request. APPENDED so session/auth are already started when it reads
        // the signed-in user's saved preference.
        $middleware->web(append: [
            \App\Http\Middleware\SetPreferences::class,
        ]);

        // Payment webhooks are server-to-server calls signed with each
        // provider's webhook secret (verified inside PaymentController), so
        // they carry no CSRF token.
        $middleware->validateCsrfTokens(except: [
            'payments/webhook',
        ]);

        // Cashfree's redirect-flow return URL (payments.cashfree.return) is a
        // GET request carrying only query params (no CSRF token) — it is safe
        // because the payment is re-verified server-side via the Cashfree API
        // before any state changes.
        $middleware->validateCsrfTokens(except: [
            'payments/cashfree/return',
        ]);

        $middleware->alias([
            'admin'            => \App\Http\Middleware\EnsureAdmin::class,
            'perm'             => \App\Http\Middleware\EnsurePermission::class,
            'delivery.partner' => \App\Http\Middleware\EnsureDeliveryPartner::class,
            'seller'           => \App\Http\Middleware\EnsureSeller::class,
            'staff'            => \App\Http\Middleware\EnsureStaff::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Payment AJAX endpoints live under /payments/* (web middleware) but
        // send Accept: application/json. Without this, abort(403/419) and other
        // HttpExceptions render as HTML <!DOCTYPE ...> and the checkout fetch()
        // crashes on res.json() with "Unexpected token '<'". Keep normal HTML
        // behaviour for pages that do NOT expect JSON.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*')
                || $request->is('payments/*')
                || $request->expectsJson()
                || $request->wantsJson(),
        );
    })->create();
