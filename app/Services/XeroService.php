<?php

namespace App\Services;

use App\ConfigRefreshXero;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;
use App\Services\GlobalService;
use Illuminate\Support\Facades\Storage;

use Illuminate\Support\Facades\Log;
class XeroService
{

    use ConfigRefreshXero;
    public ?int $minRemaining = null;
    public ?int $dayRemaining = null;

    private function trackRate($response): void
    {
        $min = $response->header('X-MinLimit-Remaining');
        if ($min === null || $min === '') {
            return;
        }

        $day = $response->header('X-DayLimit-Remaining');

        $this->minRemaining = (int) $min;
        $this->dayRemaining = (int) ($day ?? 0);

        app(GlobalService::class)->requestCalculationXero($this->minRemaining, $this->dayRemaining);
    }
    public function getInvoiceHistory(string $tenantId, string $invoiceId): array
    {
        $tokenData = $this->getValidToken();
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $tokenData['access_token'],
            'Xero-Tenant-Id' => $tenantId,
            'Accept' => 'application/json',
        ])->timeout(25)->get("https://api.xero.com/api.xro/2.0/Invoices/{$invoiceId}/History");

        if (!$response->successful()) {
            throw new \RuntimeException("Gagal ambil history invoice {$invoiceId}. HTTP {$response->status()}");
        }

        return $response->json('HistoryRecords') ?? [];
    }

    public function getUsers(string $tenantId): array
    {
        $tokenData = $this->getValidToken();
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $tokenData['access_token'],
            'Xero-Tenant-Id' => $tenantId,
            'Accept' => 'application/json',
        ])->timeout(25)->get('https://api.xero.com/api.xro/2.0/Users');

        if (!$response->successful()) {
            throw new \RuntimeException("Gagal ambil daftar user Xero. HTTP {$response->status()}");
        }

        return $response->json('Users') ?? [];
    }
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

    public function getAllInvoices(string $tenantId, int $page = 1, ?Carbon $from = null): array
    {
        $query = ['page' => $page, 'pageSize' => 100, 'order' => 'Date ASC'];

        if ($from) {
            $query['where'] = sprintf('Date>=DateTime(%d,%d,%d)', $from->year, $from->month, $from->day);
        }

        $response = Http::withHeaders($this->headers($tenantId))
            ->timeout(60)
            ->get('https://api.xero.com/api.xro/2.0/Invoices', $query);

        $this->trackRate($response);

        if (!$response->successful()) {
            throw new \RuntimeException('Gagal ambil invoice. HTTP ' . $response->status());
        }

        return $response->json('Invoices') ?? [];
    }


    public function getInvoiceAttachments(string $tenantId, string $invoiceId): array
    {
        $response = Http::withHeaders($this->headers($tenantId))
            ->get("https://api.xero.com/api.xro/2.0/Invoices/{$invoiceId}/Attachments");

        $this->trackRate($response);

        if (!$response->successful()) {
            Log::error('[XeroAttachments] gagal', [
                'invoice_id' => $invoiceId,
                'status' => $response->status(),
                'body' => $response->body(),
                'www_auth' => $response->header('WWW-Authenticate'),
            ]);
            throw new \RuntimeException("Gagal ambil attachments {$invoiceId}. HTTP " . $response->status());
        }

        return $response->json('Attachments') ?? [];
    }

    public function downloadAttachment(string $tenantId, string $invoiceId, string $fileName): string
    {
        $response = Http::withHeaders(array_merge($this->headers($tenantId), ['Accept' => '*/*']))
            ->timeout(120)
            ->get("https://api.xero.com/api.xro/2.0/Invoices/{$invoiceId}/Attachments/" . rawurlencode($fileName));

        $this->trackRate($response);

        if (!$response->successful()) {
            throw new \RuntimeException("Gagal download {$fileName}. HTTP " . $response->status());
        }

        return $response->body();
    }

    public function getTenantIdV2($accessToken)
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $accessToken,
            'Content-Type' => 'application/json'
        ])->get('https://api.xero.com/connections');

        if ($response->successful()) {
            $data = $response->json();
            // Ambil tenant pertama yang aktif
            return $data[0]['tenantId'] ?? null;
        }
        return null;
    }
    public function getValidToken2()
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
    private function headers(string $tenantId): array
    {
        //$token = $this->accessToken(); // sesuaikan dengan method token Anda
        $tokenData = $this->getValidToken2();
        return [
            'Authorization' => 'Bearer ' . $tokenData['access_token'],
            'Xero-Tenant-Id' => $tenantId,
            'Accept' => 'application/json',
        ];
    }
}
