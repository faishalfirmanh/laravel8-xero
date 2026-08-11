<?php

namespace App\Services;

use App\ConfigRefreshXero;
use Illuminate\Support\Facades\Http;

class XeroService
{

    use ConfigRefreshXero;
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


    public function getPayment(string $tenantId, string $paymentId): array
    {
        $tokenData = $this->getValidToken();

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $tokenData['access_token'],
            'Xero-Tenant-Id' => $tenantId,
            'Accept' => 'application/json',
        ])
            ->timeout(15)
            ->get("https://api.xero.com/api.xro/2.0/Payments/{$paymentId}");

        if ($response->failed()) {
            throw new \RuntimeException(
                "Gagal ambil payment {$paymentId} dari Xero: HTTP {$response->status()} - {$response->body()}"
            );
        }

        return $response->json('Payments.0', []);
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


    public function getInvoice(string $tenantId, string $invoiceId): array
    {
        $tokenData = $this->getValidToken();
        
        if (empty($tokenData['access_token'])) {
        throw new \RuntimeException('Token Xero tidak tersedia — perlu re-autentikasi ulang.');
    }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $tokenData['access_token'],
            'Xero-Tenant-Id' => $tenantId, // pakai tenantId dari event webhook, bukan hardcode
            'Accept' => 'application/json',
        ])
            ->timeout(15)
            ->get("https://api.xero.com/api.xro/2.0/Invoices/{$invoiceId}");

        if ($response->failed()) {
            throw new \RuntimeException(
                "Gagal ambil invoice {$invoiceId} dari Xero: HTTP {$response->status()} - {$response->body()}"
            );
        }

        return $response->json('Invoices.0', []);
    }
}
