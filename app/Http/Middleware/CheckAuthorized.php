<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckAuthorized
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!auth()->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized'
            ], 401);
        }

        // لو عندك شرط إضافي (مثلاً role أو is_active)
        if (!auth()->user()->is_active) {
            return response()->json([
                'status' => false,
                'message' => 'User not authorized'
            ], 403);
        }
        dd('check authorized middleware');
        return $next($request);
    }
}
