<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class PublicKey
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $adminKey = env('KEY_PUBLIC_MIDDLEWARE');

        $requestKey = $request->key;

        if ($adminKey !== $requestKey) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Invalid API Key.'
            ], 401);
        }

        return $next($request);
    }
}
