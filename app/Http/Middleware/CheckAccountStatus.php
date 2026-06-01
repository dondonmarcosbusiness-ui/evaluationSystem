<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckAccountStatus
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // If user is authenticated but not active, revoke token and reject request
        if ($user && !$user->is_active) {
            // Revoke current token
            $request->user()->currentAccessToken()->delete();

            return response()->json([
                'message' => 'Your account has been deactivated. You have been logged out.'
            ], 403);
        }

        return $next($request);
    }
}
