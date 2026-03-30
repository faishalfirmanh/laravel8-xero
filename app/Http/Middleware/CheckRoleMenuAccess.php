<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
class CheckRoleMenuAccess
{
    public function handle(Request $request, Closure $next)
    {
       $user = auth()->user();

        // Jika belum login
        if (!$user) {
            return response()->json([
                'message' => 'Unauthorized. Silakan login terlebih dahulu.'
            ], 401);
        }

        // Ambil nama route yang sedang diakses
       // $currentRouteName = $request->route() ? $request->route()->getName() : null;
        //( $request->route());//full url dari api/....
        // Jika route tidak punya nama, lewati pengecekan (biasanya untuk route public)

        $fullPrefix = Route::current()->getPrefix();
        // 2. Ekstrak bagian terakhir setelah tanda '/' (Contoh hasil: "coa")
        $lastPrefix = Str::afterLast($fullPrefix, '/');
        // Jika Anda ingin memastikan tidak ada error saat route tidak punya prefix sama sekali:
        $currentRouteName = $fullPrefix ? Str::afterLast($fullPrefix, '/') : null;//ambil prefixnya, prefix tidak boleh sama


        if (empty($currentRouteName)) {
            return $next($request);
        }

        // Cek apakah SALAH SATU role user memiliki menu dengan route_name ini
        //($currentRouteName);
        $hasAccess = $user->roles()
            ->whereHas('menus', function ($query) use ($currentRouteName) {
                $query->where('route_name', $currentRouteName)
                      ->where('is_active', 1);
            })
            ->exists();

        if (!$hasAccess) {
            return response()->json([
                'message' => 'Anda tidak memiliki akses ke menu ini.'
            ], 403);
        }

        return $next($request);
    }
}
