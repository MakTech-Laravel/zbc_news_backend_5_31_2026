<?php

namespace App\Http\Middleware\Api\V1;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

class BruteForceAttackLimitterForPostForm
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, int $maxAttempts = 10,  int $lockMinutes = 5): Response
    {
        $key = $request->user()
            ? 'limit:user:' . $request->user()->id
            : 'limit:ip:' . $request->ip();

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $seconds = RateLimiter::availableIn($key);

            return response()->json([
                'success' => false,
                'message' => "Too many attempts. Try again after {$seconds} seconds.",
                'retry_after' => $seconds,
            ], HttpStatus::HTTP_TOO_MANY_REQUESTS);
        }

        RateLimiter::hit(
            $key,
            $lockMinutes * 60
        );

        return $next($request);
    }
}
