<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceAccountType
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  ...$allowedTypes
     */
    public function handle(Request $request, Closure $next, ...$allowedTypes): Response
    {
        if (!auth()->check()) {
            return redirect('/login');
        }

        $userAccountType = auth()->user()->account_type;

        if (!in_array($userAccountType, $allowedTypes)) {
            return redirect('/')->with('error', 'You do not have permission to access this page.');
        }

        return $next($request);
    }
}
