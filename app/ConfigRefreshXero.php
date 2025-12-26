<?php

namespace App;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
trait ConfigRefreshXero
{
    //

    private $tokenFile = 'xero_token_2.json';//'xero_token.json';

    public function connect()
    {
        $scope = 'accounting.contacts accounting.contacts.read accounting.settings accounting.settings.read accounting.transactions accounting.transactions.read offline_access';

        $url = 'https://login.xero.com/identity/connect/authorize?' . http_build_query([
            'response_type' => 'code',
            'client_id' => env('XERO_PROD_CLIENT_ID'),//env('XERO_CLIENT_ID'),
            'redirect_uri' => env('XERO_REDIRECT_URL_PROD'),//env('XERO_REDIRECT_URL'),
            'scope' => $scope,
            'state' => 'SAFD2142432'
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

        $response = Http::asForm()->withBasicAuth(env('XERO_PROD_CLIENT_ID'), env('XERO_REDIRECT_URL_PROD'))
            ->post('https://identity.xero.com/connect/token', [
                'grant_type' => 'authorization_code',
                'code' => $request->code,
                'redirect_uri' => env('XERO_REDIRECT_URL_PROD'),
            ]);

        if ($response->successful()) {
            $tokens = $response->json();
            $this->saveToken($tokens); // Simpan ke file JSON
            // $res_json = [
            //     "pesan" =>"Koneksi Berhasil! Token tersimpan di storage/app/private/{$this->tokenFile}. Sekarang coba akses /xero/contacts",
            // ];
            return "Koneksi Berhasil! Token tersimpan di storage/app/private/{$this->tokenFile}. Sekarang coba akses /xero/contacts";
        }

        return response()->json($response->json(), 400);
    }

    private function getValidToken()
    {
        if (!Storage::exists($this->tokenFile)) {
            Log::error("file json config xero tidak ada");
            // $write_file = [
            //     'access_token' => '',
            //     'expires_in' => 1800,
            //     'token_type' => "Bearer",
            //     "refresh_token" => "ojguKUVyFtuzQpYduQKX52GjAAoqrtO3ymG5h08DGqc",
            //     "scope" => "accounting.contacts accounting.contacts.read accounting.settings accounting.settings.read accounting.transactions accounting.transactions.read offline_access",
            //     "expires_at" => 1765619502

            // ];
            // $path = 'private/xero_token.json';
            // Storage::disk('local')->put($path, json_encode($write_file, JSON_PRETTY_PRINT));
            return null;
        }

        $tokens = json_decode(Storage::get($this->tokenFile), true);
        if (empty($tokens))
            return null;

        // Cek Expired (Kita beri buffer 2 menit sebelum benar-benar habis)
        // 'expires_at' adalah timestamp yang kita buat saat save
        $now = Carbon::now()->timestamp;

        if (isset($tokens['expires_at']) && $now >= ($tokens['expires_at'] - 120)) {
            // Token Expired: Lakukan Refresh
            return $this->refreshToken($tokens['refresh_token']);
        }

        // Token Masih Aman
        return $tokens;
    }

    private function refreshToken($currentRefreshToken)
    {
        $response = Http::asForm()->withBasicAuth(env('XERO_PROD_CLIENT_ID'), env('XERO_PROD_CLIENT_SECRET'))
            ->post('https://identity.xero.com/connect/token', [
                'grant_type' => 'refresh_token',
                'refresh_token' => $currentRefreshToken
            ]);

        if ($response->successful()) {
            $newTokens = $response->json();
            $this->saveToken($newTokens); // Timpa file JSON dengan yang baru
            return $newTokens;
        }

        // Jika refresh gagal (misal refresh token expired juga), hapus file agar user login ulang
        Storage::delete($this->tokenFile);
        return null;
    }

    private function saveToken($tokens)
    {
        // Tambahkan field 'expires_at' manual agar mudah dicek nanti
        // expires_in biasanya 1800 detik (30 menit)
        $tokens['expires_at'] = Carbon::now()->timestamp + $tokens['expires_in'];

        Storage::put($this->tokenFile, json_encode($tokens, JSON_PRETTY_PRINT));
    }

    /**
     * Helper ambil Tenant ID
     */
    private function getTenantId($accessToken)
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $accessToken,
            'Content-Type' => 'application/json'
        ])->get('https://api.xero.com/connections');

        if ($response->successful()) {
            $data = $response->json();
            return $data[0]['tenantId'] ?? null; // Ambil tenant pertama
        }
        return null;
    }

}
