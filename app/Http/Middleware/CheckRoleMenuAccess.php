<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckRoleMenuAccess
{
    public function handle(Request $request, Closure $next)
    {
        $user = auth()->user();
        if (!$user || !$user->role) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Ambil nama route saat ini
        $currentRouteName = $request->route()->getName();

        // Cek apakah role user punya akses ke menu dengan route_name ini
        $hasAccess = $user->role->menus()
            ->where('route_name', $currentRouteName)
            ->where('is_active', true)
            ->exists();

        if (!$hasAccess) {
            return response()->json(['message' => 'Anda tidak memiliki akses ke menu ini'], 403);
        }

        return $next($request);
    }
}
