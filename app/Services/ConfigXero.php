<?php

namespace App\Servics;

use DB;

use Illuminate\Support\Facades\Http;
use Carbon\Carbon;
class ConfigXero
{
    public function getValidAccessToken()
    {
        $tokenData = DB::table('settings_xero')->first();

        if (!$tokenData) {
            throw new \Exception("Token Xero belum di-setup di database.");
        }

        if (Carbon::now()->greaterThanOrEqualTo(Carbon::parse($tokenData->expires_at)->subMinutes(1))) {
            return $this->refreshToken($tokenData->refresh_token, $tokenData->id);
        }
        return $tokenData->access_token;
    }


    private function refreshToken($currentRefreshToken, $rowId)
    {
        $clientId = env('XERO_CLIENT_ID');
        $clientSecret = env('XERO_CLIENT_SECRET');
        $authorization = base64_encode("$clientId:$clientSecret");

        $response = Http::withHeaders([
            'Authorization' => 'Basic ' . $authorization,
            'Content-Type' => 'application/x-www-form-urlencoded'
        ])->asForm()->post('https://identity.xero.com/connect/token', [
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $currentRefreshToken,
                ]);

        if ($response->failed()) {
            throw new \Exception("Gagal refresh token Xero: " . $response->body());
        }

        $newTokens = $response->json();

        DB::table('settings_xero')->where('id', $rowId)->update([
            'access_token' => $newTokens['access_token'],
            'refresh_token' => $newTokens['refresh_token'],
            'expires_at' => Carbon::now()->addSeconds($newTokens['expires_in']),
            'updated_at' => now(),
        ]);

        return $newTokens['access_token'];
    }


}
