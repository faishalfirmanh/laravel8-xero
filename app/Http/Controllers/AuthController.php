<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use App\Models\Config\RoleUsers;
use App\Models\Config\RoleMenus;
use App\Models\MasterData\Menu;
use Illuminate\Support\Facades\Session;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        // Cek User dan Password
        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Email atau Password salah.'
            ], 401);
        }

        $expired_at = Carbon::now()->addMinutes(720);

        // Hapus token lama (opsional, agar 1 device 1 token)
        // $user->tokens()->delete();

        // Buat Token Baru
        $token = $user->createToken('auth_token', ['*'], $expired_at)->plainTextToken;

        $role_users = RoleUsers::where('user_id', $user->id)->pluck('role_id');
        $roles_menu = RoleMenus::whereIn('role_id', $role_users)->pluck('menu_id');

        // $view_menu = Menu::whereIn('id', [4, 5, 6, 12, 13, 14, 1, 3])
        //     ->where('is_active', 1)
        //     ->orderBy('urutan', 'asc')
        //     ->get();

        $view_menu = Menu::whereIn('id', $roles_menu)
    ->where('is_active', 1)
    ->orderBy('urutan', 'asc')
    ->get();

// === TRANSFORMASI MENJADI HIRARKI (PARENT + CHILDREN) ===
    $parents = $view_menu->whereNull('parent_id')->sortBy('urutan');

    $groupedChildren = $view_menu->whereNotNull('parent_id')
        ->groupBy('parent_id')
        ->map(fn($group) => $group->sortBy('urutan'));

    $menuTree = $parents->map(function ($parent) use ($groupedChildren) {
        $children = $groupedChildren->get($parent->id, collect());

        return [
            'id'          => $parent->id,
            'nama_menu'   => $parent->nama_menu,
            'slug'        => $parent->slug,
            'route_name'  => $parent->route_name,
            'module'      => $parent->module,
            'parent_id'   => $parent->parent_id,
            'urutan'      => $parent->urutan,
            'is_active'   => $parent->is_active,
            'created_at'  => $parent->created_at,
            'updated_at'  => $parent->updated_at,
            'children'    => $children->map(function ($child) {
                return [
                    'id'          => $child->id,
                    'nama_menu'   => $child->nama_menu,
                    'slug'        => $child->slug,
                    'route_name'  => $child->route_name,
                    'module'      => $child->module,
                    'parent_id'   => $child->parent_id,
                    'urutan'      => $child->urutan,
                    'is_active'   => $child->is_active,
                    'created_at'  => $child->created_at,
                    'updated_at'  => $child->updated_at,
                ];
            })->values()->toArray()
        ];
    })->values()->toArray();


        Session::put('user_menu', $menuTree);
        Session::put('user_profile', $user);
        Session::save();

        //dd($view_menu);

        return response()->json([
            'status' => 'success',
            'message' => 'Login Berhasil',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'expires_at' => $expired_at->toDateTimeString(),
            'user' => $user,
            'menu' => $menuTree
        ]);
    }

    public function logout(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'User tidak terautentikasi'
            ], 401);
        }

        $user->currentAccessToken()->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Logout berhasil'
        ]);
    }


    public function myaccount(Request $request)
    {

        $data = User::find($request->user_login->id);

        return response()->json([
            'status' => 'success',
            'user' => $data,
            'menu' => $request->menu
        ]);
    }
}
