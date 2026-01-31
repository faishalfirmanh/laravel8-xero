<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use stdClass;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;
class XssSanitization
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
        $input = $request->all();
        $user = Auth::guard('sanctum')->user();
        array_walk_recursive($input, function (&$input) {
            if (!is_null($input)) {
                $input = strip_tags($input);
            }
        });
        $request->merge(['user_login'=>$user]);
        $request->merge($input);
        return $next($request);
    }
}
