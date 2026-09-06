<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ensures only authenticated operational staff accounts proceed.
 *
 * Server-side authorization for the /staff area — staff members are NOT
 * admins, so this middleware is deliberately separate from EnsureAdmin and
 * checks the dedicated "staff" account type only. Hiding links in Blade is
 * never relied upon.
 */
class EnsureStaff
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! auth()->check()) {
            return redirect()->route('login');
        }

        if (! auth()->user()->isStaffMember()) {
            abort(403, 'Only staff members can access this area.');
        }

        // Disabled staff accounts may not use the staff area.
        if (! auth()->user()->isActive()) {
            abort(403, 'Your staff account is disabled.');
        }

        return $next($request);
    }
}
