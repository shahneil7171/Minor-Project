<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ensures only authenticated delivery-partner accounts proceed.
 *
 * Server-side authorization for the /delivery area — hiding links in Blade
 * is never relied upon.
 */
class EnsureDeliveryPartner
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! auth()->check()) {
            return redirect()->route('login');
        }

        if (! auth()->user()->isDeliveryPartner()) {
            abort(403, 'Only delivery partners can access this area.');
        }

        // Disabled delivery partners may not use the delivery dashboard.
        if (! auth()->user()->isActive()) {
            abort(403, 'Your delivery partner account is disabled.');
        }

        return $next($request);
    }
}
