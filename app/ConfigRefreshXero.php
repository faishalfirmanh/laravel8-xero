<?php

namespace App;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

trait ConfigRefreshXero
{
    // Lokasi: storage/app/xero_token_2.json
    private $tokenFile = 'xero_token_2.json';

    public function connect()
    {
        // Scope sudah benar (termasuk openid, profile, email untuk get nama user)
        $scope = 'openid profile email accounting.contacts accounting.settings accounting.transactions offline_access';

        $url = 'https://login.xero.com/identity/connect/authorize?' . http_build_query([
            'response_type' => 'code',
            'client_id'     => env('XERO_PROD_CLIENT_ID'),
            'redirect_uri'  => env('XERO_REDIRECT_URL_PROD'),
            'scope'         => $scope,
            'state'         => 'SAFD2142432' // Sebaiknya generate string acak dinamis (Str::random)
        ]);

        return redirect($url);
    }

    /**
     * 2. Callback: Menukar "Code" dengan "Token" & Simpan ke JSON
     */
    public function callback(Request $request)
    {
        if (!$request->has('code')) {
            return 'Gagal: Tidak ada code dari Xero.';
        }

        // PERBAIKAN FATAL: Parameter ke-2 wajib CLIENT_SECRET, bukan REDIRECT_URL
        $response = Http::asForm()->withBasicAuth(env('XERO_PROD_CLIENT_ID'), env('XERO_PROD_CLIENT_SECRET'))
            ->post('https://identity.xero.com/connect/token', [
                'grant_type'   => 'authorization_code',
                'code'         => $request->code,
                'redirect_uri' => env('XERO_REDIRECT_URL_PROD'),
            ]);

        if ($response->successful()) {
            $tokens = $response->json();
            $this->saveToken($tokens); // Simpan ke storage/app/xero_token_2.json

            return "Koneksi Berhasil! Token tersimpan di storage/app/{$this->tokenFile}.";
        }

        return response()->json($response->json(), 400);
    }

    public function getUserNameXeroFromToken($accessToken)
    {
        // Debugging dihapus (dd) agar script jalan terus.
        $response = Http::withToken($accessToken)
            ->withHeaders([
                'Accept'       => 'application/json',
                'Content-Type' => 'application/json'
            ])
            ->get('https://identity.xero.com/connect/userinfo');

        if ($response->successful()) {
            $data = $response->json();

            $givenName  = $data['given_name']  ?? '';
            $familyName = $data['family_name'] ?? '';
            $email      = $data['email']       ?? 'Email tidak tersedia';

            $fullName = trim("$givenName $familyName");

            if ($fullName) {
                return "$fullName ($email)";
            }

            return $email;
        }

        // Return error message asli dari Xero untuk debugging jika gagal
        return "Gagal ambil user: " . $response->body();
    }


    private function getValidToken()
    {
        // Cek apakah file ada di storage/app/
        if (!Storage::exists($this->tokenFile)) {
            Log::error("File json config xero tidak ditemukan di: " . storage_path('app/' . $this->tokenFile));
            return null;
        }

        $tokens = json_decode(Storage::get($this->tokenFile), true);

        if (empty($tokens)) {
            return null;
        }

        // Cek Expired
        $now = Carbon::now()->timestamp;

        // Jika token akan expired dalam 2 menit (atau sudah expired), refresh sekarang
        if (isset($tokens['expires_at']) && $now >= ($tokens['expires_at'] - 120)) {
            Log::info("Xero Token Expired, melakukan refresh token...");
            return $this->refreshToken($tokens['refresh_token']);
        }

        // Token Masih Aman
        return $tokens;
    }

    private function refreshToken($currentRefreshToken)
    {
        // Pastikan Client Secret benar di sini juga
        $response = Http::asForm()->withBasicAuth(env('XERO_PROD_CLIENT_ID'), env('XERO_PROD_CLIENT_SECRET'))
            ->post('https://identity.xero.com/connect/token', [
                'grant_type'    => 'refresh_token',
                'refresh_token' => $currentRefreshToken
            ]);

        if ($response->successful()) {
            $newTokens = $response->json();
            $this->saveToken($newTokens); // Update file JSON
            return $newTokens;
        }

        // Jika refresh gagal (misal refresh token juga sudah basi), hapus file agar user dipaksa login ulang
        Log::error("Gagal refresh token Xero. Menghapus file token.");
        Storage::delete($this->tokenFile);
        return null;
    }

    private function saveToken($tokens)
    {
        // Tambahkan 'expires_at' (Timestamp sekarang + expires_in detik)
        // expires_in dari Xero biasanya 1800 detik (30 menit)
        if (isset($tokens['expires_in'])) {
            $tokens['expires_at'] = Carbon::now()->timestamp + $tokens['expires_in'];
        }

        // Simpan ke storage/app/xero_token_2.json
        Storage::put($this->tokenFile, json_encode($tokens, JSON_PRETTY_PRINT));
    }

    private function getTenantId($accessToken)
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $accessToken,
            'Content-Type'  => 'application/json'
        ])->get('https://api.xero.com/connections');

        if ($response->successful()) {
            $data = $response->json();
            // Ambil tenant pertama yang aktif
            return $data[0]['tenantId'] ?? null;
        }
        return null;
    }
}
