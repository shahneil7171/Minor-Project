<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePermission
{
    /**
     * Ensure the authenticated staff user holds a granular permission.
     *
     * Usage: ->middleware('perm:catalog,create') checks "catalog.create".
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $module, string $action): Response
    {
        if (! auth()->check() || ! auth()->user()->hasPermission($module . '.' . $action)) {
            abort(403, 'You do not have permission to perform this action.');
        }

        return $next($request);
    }
}
