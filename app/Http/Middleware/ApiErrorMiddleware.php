<?php

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;
class ApiErrorMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle($request, Closure $next)
    {
        try {
            $request->headers->set('Accept', 'application/json');
            return $next($request);
        } catch (Throwable $e) {
            return ApiResponse::error(
                app()->isProduction()
                ? 'Service temporarily unavailable'
                : $e->getMessage(),
                500
            );
        }
    }
}
