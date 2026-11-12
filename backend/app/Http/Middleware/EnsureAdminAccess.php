<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Prevents non-admin authenticated accounts (Customer, Vendor, Delivery Partner)
 * from escalating privileges to `/admin/*` governance or financial endpoints.
 */
class EnsureAdminAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->role !== 'admin') {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Administrative privileges are required for this action.',
                ], 403);
            }

            abort(403, 'Administrative privileges are required to access this governance desk.');
        }

        if (!$user && !app()->runningUnitTests() && app()->environment('production')) {
            return redirect('/login')->withErrors([
                'identifier' => 'Please sign in as an Administrator (or click the Admin 1-Click Demo Login chip below) to access the Admin Console.',
            ]);
        }

        return $next($request);
    }
}
