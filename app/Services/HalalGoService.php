<?php

namespace App\Services;


use App\Models\GoHalal\PaketUmroh;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Client\ConnectionException;
class HalalGoService
{
    const CACHE_KEY = 'halal_go_token';

    private string $baseUrl;   // endpoint API (create-package, dll)
    private string $authUrl;   // endpoint auth/token

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.halal.base_url'), '/');
        $this->authUrl = rtrim(config('services.halal.base_url_v2'), '/');
    }

    /**
     * Ambil token dari cache, atau minta token baru ke get-token.
     */

    private function http(int $timeout)
    {
        return Http::acceptJson()
            ->withOptions(['verify' => config('services.halal.ca_bundle') ?: true])
            ->timeout($timeout);
    }

    private function elapsed(float $start): int
    {
        return (int) round((microtime(true) - $start) * 1000);
    }

    private function snippet(?string $body, int $limit = 1000): string
    {
        $body = (string) $body;
        return mb_strlen($body) > $limit ? mb_substr($body, 0, $limit) . '…[dipotong]' : $body;
    }

    public function getToken(bool $forceNew = false): ?string
    {
        if (!$forceNew && ($cached = Cache::get(self::CACHE_KEY))) {
            Log::debug('[HalalGo] get-token: pakai token dari cache');
            return $cached;
        }

        $start = microtime(true);

        try {
            $res = $this->http(30)->post($this->authUrl, [
                'client_id' => config('services.halal.client_id'),
                'client_key' => config('services.halal.client_key'),
            ]);
        } catch (ConnectionException $e) {
            Log::error('[HalalGo] get-token: koneksi gagal', [
                'url' => $this->authUrl,
                'duration_ms' => $this->elapsed($start),
                'error' => $e->getMessage(),
            ]);
            return null;
        }

        $body = $res->json();
        $duration = $this->elapsed($start);

        if (!in_array($res->status(), [200, 201], true) || !data_get($body, 'status')) {
            Log::error('[HalalGo] get-token: gagal', [
                'url' => $this->authUrl,
                'http_status' => $res->status(),
                'message' => data_get($body, 'message'),
                'duration_ms' => $duration,
                'body' => $this->snippet($res->body()),
            ]);
            return null;
        }

        $token = data_get($body, 'data.token');
        $expired = data_get($body, 'data.expired');

        if (!$token) {
            Log::error('[HalalGo] get-token: respons sukses tapi token kosong', [
                'http_status' => $res->status(),
                'duration_ms' => $duration,
                'body' => $this->snippet($res->body()),
            ]);
            return null;
        }

        // Simpan sampai 60 detik sebelum expired
        $ttl = 0;
        if ($expired) {
            try {
                $ttl = max(0, Carbon::parse($expired, 'Asia/Jakarta')->timestamp - now()->timestamp - 60);
            } catch (\Throwable $e) {
                Log::warning('[HalalGo] get-token: format expired tidak valid', ['expired' => $expired]);
            }
        }

        if ($ttl > 0) {
            Cache::put(self::CACHE_KEY, $token, $ttl);
        } else {
            Log::warning('[HalalGo] get-token: token tidak di-cache (TTL 0), cek timezone/format expired', [
                'expired' => $expired,
            ]);
        }

        Log::info('[HalalGo] get-token: sukses', [
            'http_status' => $res->status(),
            'duration_ms' => $duration,
            'expired' => $expired,
            'ttl_detik' => $ttl,
            'cached' => $ttl > 0,
            'force_new' => $forceNew,
        ]);

        return $token;
    }

    /**
     * Kirim paket ke create-package.
     * Return: ['ok' => bool, 'http_status' => int, 'kode_paket' => ?, 'status_ghg' => ?, 'message' => ?]
     */
    public function createPackage(array $payload): array
    {
        $ctx = [
            'nama_paket' => $payload['nama_paket'] ?? null,
            'tipe' => $payload['tipe'] ?? null,
            'tgl_berangkat' => $payload['tgl_berangkat'] ?? null,
            'jumlah_hotel' => count($payload['hotels'] ?? []),
        ];

        $token = $this->getToken();
        if (!$token) {
            Log::error('[HalalGo] create-package: dibatalkan, token tidak didapat', $ctx);
            return ['ok' => false, 'http_status' => 0, 'message' => 'Gagal mendapatkan token'];
        }

        $start = microtime(true);

        try {
            Log::debug('[HalalGo] create-package: cek itinerary', [
                'tipe_data' => gettype($payload['itinerary'] ?? null),
                'panjang' => is_string($payload['itinerary'] ?? null) ? mb_strlen($payload['itinerary']) : null,
                'ada_html' => is_string($payload['itinerary'] ?? null) && $payload['itinerary'] !== strip_tags($payload['itinerary']),
                'ada_emoji' => is_string($payload['itinerary'] ?? null) && (bool) preg_match('/[\x{1F000}-\x{1FFFF}\x{2600}-\x{27BF}]/u', $payload['itinerary']),
                'cuplikan' => is_string($payload['itinerary'] ?? null) ? mb_substr($payload['itinerary'], 0, 300) : $payload['itinerary'] ?? null,
            ]);

            $res = $this->post('/create-package', $payload, $token);

            // Token bisa habis sebelum waktunya: minta token baru sekali lalu ulangi
            if ($res->status() === 401) {
                Log::warning('[HalalGo] create-package: 401, token diperbarui lalu dicoba ulang', $ctx);

                $token = $this->getToken(true);
                if (!$token) {
                    Log::error('[HalalGo] create-package: gagal memperbarui token', $ctx);
                    return ['ok' => false, 'http_status' => 401, 'message' => 'Gagal memperbarui token'];
                }
                $res = $this->post('/create-package', $payload, $token);
            }
        } catch (ConnectionException $e) {
            Log::error('[HalalGo] create-package: koneksi gagal', $ctx + [
                'url' => $this->baseUrl . '/create-package',
                'duration_ms' => $this->elapsed($start),
                'error' => $e->getMessage(),
            ]);
            return ['ok' => false, 'http_status' => 0, 'message' => 'Koneksi ke Halal Go gagal'];
        }

        $body = $res->json() ?? [];
        $duration = $this->elapsed($start);

        if ($res->status() === 201 && data_get($body, 'status') === true) {
            $result = [
                'ok' => true,
                'http_status' => 201,
                'kode_paket' => data_get($body, 'data.kode_paket'),
                'status_ghg' => data_get($body, 'data.status_ghg'),
                'message' => data_get($body, 'message'),
            ];

            Log::info('[HalalGo] create-package: sukses', $ctx + $result + ['duration_ms' => $duration]);
            return $result;
        }

        $errors = data_get($body, 'data.errors', []);

        $result = [
            'ok' => false,
            'http_status' => $res->status(),
            'message' => data_get($body, 'message', 'Respon tidak dikenali'),
            'errors' => is_array($errors) ? $errors : [(string) $errors],
        ];

        Log::error('[HalalGo] create-package: gagal', $ctx + $result + [
            'url' => $this->baseUrl . '/create-package',
            'duration_ms' => $duration,
            'body' => $this->snippet($res->body()),
        ]);

        return $result;
    }

    private function post(string $path, array $payload, string $token)
    {
        return Http::acceptJson()
            ->withOptions(['verify' => config('services.halal.ca_bundle') ?: true])
            ->withToken($token)
            ->timeout(60)
            ->post($this->baseUrl . $path, $payload);
    }

    /**
     * Susun payload sesuai format Halal Go dari data paket yang sudah tersimpan.
     */
    public function buildPayload(PaketUmroh $paket): array
    {
        $paket->loadMissing('hotels');

        return [
            'tipe' => $paket->tipe,
            'nama_paket' => $paket->nama_paket,
            'tgl_berangkat' => $paket->tgl_berangkat instanceof \DateTimeInterface
                ? $paket->tgl_berangkat->format('Y-m-d')
                : (string) $paket->tgl_berangkat,
            'jumlah_hari' => (int) $paket->jumlah_hari,
            'maskapai' => $paket->maskapai,
            'keberangkatan' => $paket->keberangkatan,
            'landing' => $paket->landing,
            'kodeflight' => $paket->kodeflight ?? '',
            'hrginfant' => (float) $paket->hrginfant,
            'hrgquad' => (float) $paket->hrgquad,
            'hrgtriple' => (float) $paket->hrgtriple,
            'hrgdouble' => (float) $paket->hrgdouble,
            'hrgperlengkapan' => (float) $paket->hrgperlengkapan,
            'imgpaketumroh' => 'https://drive.google.com/uc?export=download&id=1YPxvOLIIW9ebPx1OLqC-R3GGZA24F4Yt',//$paket->imgpaketumroh ?? '',
            'itinerary' => 'https://pdfobject.com/pdf/sample.pdf',//$paket->itinerary ?? '',
            'max_slot' => (int) $paket->max_slot,
            'available_slot' => (int) $paket->available_slot,
            'filled_slot' => (int) $paket->filled_slot,
            'hotels' => $paket->hotels->map(fn($h) => [
                'idhotelmitra' => $h->hotel_id,
                'kota' => $h->kota,
                'nama_hotel' => $h->nama_hotel,
                'alamat' => $h->alamat ?? '',
                'jarak' => $h->jarak ?? '',
                'bintang' => $h->bintang ?? '',
                'night' => (int) $h->night,
            ])->values()->all(),
        ];
    }
}