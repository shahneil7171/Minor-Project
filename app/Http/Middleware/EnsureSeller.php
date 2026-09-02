<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ensures only authenticated seller accounts proceed.
 *
 * Server-side authorization for the /seller order area — buyers, delivery
 * partners and guests are rejected before any controller logic runs.
 */
class EnsureSeller
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! auth()->check()) {
            return redirect()->route('login');
        }

        if (! auth()->user()->isSeller()) {
            abort(403, 'Only sellers can access this area.');
        }

        return $next($request);
    }
}
