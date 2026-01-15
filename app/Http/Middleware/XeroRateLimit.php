<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Cache;

class XeroRateLimit
{
    public function handle($request, Closure $next)
    {
        $key = 'xero_rate_limit';
        $count = Cache::get($key, 0);

        if ($count >= 55) { // safe buffer
            return response()->json([
                'message' => 'Xero rate limit protection active'
            ], 429);
        }
        Cache::put($key, $count + 1, now()->addMinute());
        return $next($request);
    }
}
