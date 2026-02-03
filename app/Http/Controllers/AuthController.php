<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
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
        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Email atau Password salah.'
            ], 401);
        }

        $expired_at = Carbon::now()->addHours(5);//Carbon::now()->addDay();
        $tokenResult = $user->createToken('auth_token', ['*'], $expired_at);

        // Hapus token lama (opsional, agar 1 device 1 token)
        // $user->tokens()->delete();

        // Buat Token Baru
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'status' => 'success',
            'message' => 'Login Berhasil',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'expires_at' => $expired_at->toDateTimeString(),
            'user' => $user
        ]);
    }

    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'      => 'required|string|max:255',
            'email'     => 'required|string|email|max:255|unique:users',
            'password'  => 'required|string|min:8|confirmed', // 'confirmed' butuh field 'password_confirmation'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Validasi Gagal',
                'errors'  => $validator->errors()
            ], 422);
        }

        // 2. Buat User Baru
        $user = User::create([
            'name'      => $request->name,
            'email'     => $request->email,
            'password'  => Hash::make($request->password), // Wajib di-Hash!
        ]);

        // 3. (Opsional) Langsung buat token agar user tidak perlu login ulang
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'status'        => 'success',
            'message'       => 'Registrasi Berhasil',
            'data'          => $user,
            'access_token'  => $token,
            'token_type'    => 'Bearer'
        ], 201);
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

        $user->tokens()->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Logout dari semua device berhasil'
        ]);
    }
}
