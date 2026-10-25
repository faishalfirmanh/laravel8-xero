<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Validasi header "Authorization: Bearer <token>" untuk callback dari Halal Go.
 * Token dibandingkan dengan config('services.halal.callback_token') (env HALALGO_CALLBACK_TOKEN).
 * Jika token belum diset di server, semua request ditolak (fail closed).
 */
class VerifyHalalGoCallback
{
    public function handle(Request $request, Closure $next)
    {
        $expected = (string) config('services.halal.callback_token');
        $given = (string) $request->bearerToken();

        if ($expected === '' || $given === '' || !hash_equals($expected, $given)) {
            return response()->json([
                'status' => false,
                'message' => 'Authorization Bearer hilang/tidak valid',
            ], 401);
        }

        return $next($request);
    }
}