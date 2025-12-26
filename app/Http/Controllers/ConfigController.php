<?php

namespace App\Http\Controllers;

use App\Servics\ConfigXero;
use App\Models\ConfigSettingXero;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;
use Str;
class ConfigController extends Controller
{


    public function redirect()
    {
        $config = ConfigSettingXero::first();
        if (!$config) return "Config belum diisi di database";

        // SCOPE 'offline_access' ADALAH KUNCI AGAR TOKEN BISA DI-REFRESH
        $scope = 'offline_access accounting.contacts accounting.settings';
        
        $url = "https://login.xero.com/identity/connect/authorize?" . http_build_query([
            'response_type' => 'code',
            'client_id' => $config->client_id,
            'redirect_uri' => $config->redirect_url,
            'scope' => $scope,
            'state' => '12sx233'
        ]);

        return redirect($url);
    }

    public function callback(Request $request)
    {
        $config = ConfigSettingXero::first();
        $code = $request->input('code');

        if (!$code) return "Gagal mendapatkan Authorization Code";

        $response = Http::asForm()
            ->withBasicAuth($config->client_id, $config->client_secret)
            ->post('https://identity.xero.com/connect/token', [
                'grant_type' => 'authorization_code',
                'code' => 't2CSDHEm3D4qgur7N8HYkuIpZKCs_buPkLmmZ8CThfs',
                'redirect_uri' => $config->redirect_url,
            ]);

        $data = $response->json();

        if (isset($data['error'])) {
            return response()->json($data);
        }

        $config->update([
            'access_token'  => $data['access_token'],
            'barer_token'   => $data['access_token'], // Duplicate sesuai kolom Anda
            'refresh_token' => $data['refresh_token'],
            'id_token'      => $data['id_token'] ?? null,
            'code'          => $code, // Opsional disimpan
            'xero_tenant_id'=> null, // Nanti diambil terpisah atau dari API connection
            'expires_at'    => Carbon::now()->addSeconds($data['expires_in']),
        ]);

        return "Berhasil! Token tersimpan. Siap digunakan.";
    }

    public function getToken(Request $request)
    {
        try {
            // 1. Validasi input code
            if (!$request->has('code')) {
                return response()->json(['message' => 'Authorization code missing'], 400);
            }

            $code = $request->code;

            // 2. Siapkan Basic Auth Header (Sesuai standar Xero)
            $clientId = env('XERO_CLIENT_ID');
            $clientSecret = env('XERO_CLIENT_SECRET');
            $authorization = base64_encode("$clientId:$clientSecret");

            // 3. Request ke Xero (Wajib asForm)
            $response = Http::withHeaders([
                'Authorization' => 'Basic ' . $authorization,
                'Content-Type' => 'application/x-www-form-urlencoded'
            ])->asForm()->post('https://identity.xero.com/connect/token', [
                        'grant_type' => 'authorization_code',
                        'code' => $code,
                        // Pastikan ini SAMA PERSIS dengan yang dipakai saat generate Auth URL
                        'redirect_uri' => env('XERO_REDIRECT_URI', 'https://localhost'),
                    ]);

            if ($response->failed()) {
                return response()->json([
                    'message' => 'Gagal menukar token',
                    'error' => $response->json()
                ], $response->status());
            }

            $tokens = $response->json();

            // 4. PENTING: Simpan Token ke Database!
            // Gunakan updateOrInsert agar jika sudah ada record, dia mengupdate.
            // Asumsi tabel 'settings_xero' hanya punya 1 baris untuk config global.

            // Cek apakah tabel kosong atau sudah ada isinya, kita ambil ID pertama kalau ada
            $exist = \DB::table('settings_xero')->first();
            $id = $exist ? $exist->id : 1;

            \DB::table('settings_xero')->updateOrInsert(
                ['id' => $id], // Kondisi (Update ID ini)
                [
                    'access_token' => $tokens['access_token'],
                    'refresh_token' => $tokens['refresh_token'],
                    'id_token' => $tokens['id_token'] ?? null,
                    // Hitung waktu expired (biasanya now + 1800 detik)
                    'expires_at' => Carbon::now()->addSeconds($tokens['expires_in']),
                    'updated_at' => now(),
                    // Jika baris baru, created_at perlu diisi
                    'created_at' => now(),
                ]
            );

            return response()->json([
                'message' => 'Token berhasil disimpan!',
                'data' => $tokens // Opsional, untuk debug saja
            ]);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Server Error: ' . $e->getMessage()], 500);
        }
    }



    public function getAuthUrl()
    {
        // 1. Tentukan Parameter
        // Scope sesuai request Anda
        $scopes = 'offline_access accounting.contacts accounting.settings';

        // State sebaiknya random string untuk keamanan (mencegah CSRF)
        $state = Str::random(16);

        // 2. Susun Query Parameters
        $query = http_build_query([
            'response_type' => 'code',
            'client_id' => env('XERO_CLIENT_ID'),
            'redirect_uri' => env('XERO_REDIRECT_URI'),
            'scope' => $scopes,
            'state' => $state,
        ]);

        // 3. Gabungkan dengan Base URL Xero
        $authorizationUrl = 'https://login.xero.com/identity/connect/authorize?' . $query;

        // 4. Return sebagai JSON
        return response()->json([
            'message' => 'Silakan redirect user ke URL ini',
            'auth_url' => $authorizationUrl,
            'state' => $state // Simpan ini di frontend/session untuk verifikasi nanti
        ]);
    }





}
