<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;

class MekariAuthService
{
    protected string $baseUrl;
    protected string $clientId;
    protected string $clientSecret;

    public function __construct()
    {
        $this->baseUrl = config('services.mekari.base_url');
        $this->clientId = config('services.mekari.client_id');
        $this->clientSecret = config('services.mekari.client_secret');
    }

    protected function generateHeaders(string $method, string $pathWithQuery): array
    {
        $datetime = Carbon::now()->toRfc7231String();
        $requestLine = "{$method} {$pathWithQuery} HTTP/1.1";
        $payload = "date: {$datetime}\n{$requestLine}";
        $digest = hash_hmac('sha256', $payload, $this->clientSecret, true);
        $signature = base64_encode($digest);

        return [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'Date' => $datetime,
            'Authorization' => "hmac username=\"{$this->clientId}\", algorithm=\"hmac-sha256\", headers=\"date request-line\", signature=\"{$signature}\"",
        ];
    }

    public function post(string $path, array $body = [])
    {
        return Http::withHeaders($this->generateHeaders('POST', $path))
            ->baseUrl($this->baseUrl)
            ->post($path, $body);
    }

    /**
     * NOTE: path ini rekonstruksi dari referensi SDK resmi Mekari, belum saya
     * verifikasi 100% dari dokumentasi mentah. Cek dulu di menu "Documentation"
     * dashboard developer kamu - kalau beda, tinggal ganti string path di bawah.
     */
    public function sendWhatsappBroadcastDirect(array $payload)
    {
        return $this->post('/qontak/chat/v1/broadcasts/whatsapp/direct', $payload);
    }
}