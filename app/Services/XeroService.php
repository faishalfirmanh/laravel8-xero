<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class XeroService
{
    public static function getAccessToken()
    {
        $expiredAt = env('XERO_TOKEN_EXPIRED_AT');

        if (!$expiredAt || now()->gte($expiredAt)) {
            $response = Http::asForm()->post(
                'https://identity.xero.com/connect/token',
                [
                    'grant_type' => 'refresh_token',
                    'refresh_token' => env('XERO_REFRESH_TOKEN'),
                    'client_id' => env('XERO_CLIENT_ID'),
                    'client_secret' => env('XERO_CLIENT_SECRET'),
                ]
            )->json();

            if (!isset($response['access_token'])) {
                throw new \Exception('Gagal refresh token Xero');
            }

            self::updateEnv([
                'XERO_ACCESS_TOKEN' => $response['access_token'],
                'XERO_REFRESH_TOKEN' => $response['refresh_token'],
                'XERO_TOKEN_EXPIRED_AT' => now()->addSeconds($response['expires_in'] - 60),
            ]);

            return $response['access_token'];
        }

        return env('XERO_ACCESS_TOKEN');
    }

    private static function updateEnv(array $data)
    {
        $envPath = base_path('.env');
        $env = file_get_contents($envPath);

        foreach ($data as $key => $value) {
            $env = preg_replace(
                "/^{$key}=.*$/m",
                "{$key}=\"{$value}\"",
                $env
            );
        }

        file_put_contents($envPath, $env);
    }
}
