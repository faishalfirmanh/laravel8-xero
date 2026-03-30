<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use stdClass;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;
use App\Models\Config\RoleUsers;
use App\Models\Config\RoleMenus;
use App\Models\MasterData\Menu;
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
        $role_users = RoleUsers::where('user_id', $user->id)->pluck('role_id');
        $roles_menu = RoleMenus::whereIn('role_id',$role_users)->pluck('menu_id');
        $view_menu = Menu::with('children')->whereIn('id',$roles_menu)
            ->where('is_active',1)
            ->where('parent_id',null)
            ->orderBy('urutan','asc')->get();

        $request->merge(['menu'=>$view_menu]);
        $request->merge($input);
        return $next($request);
    }
}
