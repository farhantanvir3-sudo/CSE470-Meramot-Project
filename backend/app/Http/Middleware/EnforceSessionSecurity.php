<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enforces active-account session validity and baseline HTTP security headers.
 */
class EnforceSessionSecurity
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            $user = $request->user();

            // Restricted (`status === 'banned'`) accounts retain read-only order tracking (R19),
            // whereas deleted or deactivated accounts are immediately signed out.
            if ($user && ($user->status === 'deleted' || ($user->status !== 'banned' && !$user->is_active))) {
                $isDelivery = in_array($user->role, ['delivery', 'delivery_agent'], true);

                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                if ($request->expectsJson() || $request->is('api/*')) {
                    return response()->json([
                        'message' => 'Your session ended because this account is inactive.',
                    ], 401);
                }

                $target = $isDelivery ? '/delivery/login' : '/login';
                $field = $isDelivery ? 'company_email' : 'identifier';

                return redirect($target)->withErrors([
                    $field => 'Your session ended because this account is inactive.',
                ]);
            }
        }

        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'SAMEORIGIN', false);
        $response->headers->set('X-Content-Type-Options', 'nosniff', false);
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin', false);

        return $response;
    }
}
