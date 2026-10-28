<?php

namespace App\Services;

use App\Models\GoHalal\PaketUmroh;
use Carbon\Carbon;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

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

    // ================================================================
    // HELPER UMUM
    // ================================================================

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

    private function snippet($body, int $limit = 1000): string
    {
        $body = is_string($body) ? $body : (string) json_encode($body);
        return mb_strlen($body) > $limit ? mb_substr($body, 0, $limit) . '…[dipotong]' : $body;
    }

    private function fail(int $status, string $message, array $errors = []): array
    {
        return [
            'ok' => false,
            'http_status' => $status,
            'message' => $message,
            'errors' => $errors,
        ];
    }

    /**
     * Paket dianggap SUDAH ada di Halal Go bila random_uuid_paket berisi kode_paket
     * dari Halal Go (bukan UUID lokal hasil generateUniqueRandomUuid()).
     */
    public function hasKodePaket(PaketUmroh $paket): bool
    {
        $kode = (string) $paket->random_uuid_paket;

        return $kode !== '' && !Str::isUuid($kode);
    }

    // ================================================================
    // TOKEN
    // ================================================================

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

    // ================================================================
    // ENDPOINT
    // ================================================================

    /**
     * POST /create-package
     * Return: ['ok','http_status','kode_paket','status_ghg','message'] atau ['ok'=>false,...,'errors'=>[]]
     */
    public function createPackage(array $payload): array
    {
        $ctx = [
            'nama_paket' => $payload['nama_paket'] ?? null,
            'tipe' => $payload['tipe'] ?? null,
            'tgl_berangkat' => $payload['tgl_berangkat'] ?? null,
            'jumlah_hotel' => count($payload['hotels'] ?? []),
        ];

        return $this->call(
            'create-package',
            '/create-package',
            fn(string $token) => $this->http(60)->withToken($token)
                ->post($this->baseUrl . '/create-package', $payload),
            $ctx
        );
    }

    /**
     * POST /update-package
     * Payload sama dengan create-package + kode_paket.
     * Dokumentasi update-package TIDAK punya field itinerary -> pakai uploadItinerary().
     */
    public function updatePackage(string $kodePaket, array $payload): array
    {
        unset($payload['itinerary']);
        $payload = ['kode_paket' => $kodePaket] + $payload;

        $ctx = [
            'kode_paket' => $kodePaket,
            'nama_paket' => $payload['nama_paket'] ?? null,
            'jumlah_hotel' => count($payload['hotels'] ?? []),
        ];

        return $this->call(
            'update-package',
            '/update-package',
            fn(string $token) => $this->http(60)->withToken($token)
                ->post($this->baseUrl . '/update-package', $payload),
            $ctx
        );
    }

    /**
     * POST /update-seat
     */
    public function updateSeat(string $kodePaket, int $maxSlot, int $availableSlot, int $filledSlot): array
    {
        $payload = [
            'kode_paket' => $kodePaket,
            'max_slot' => max(0, $maxSlot),
            'available_slot' => max(0, $availableSlot), // syncSlot() bisa negatif bila overbook
            'filled_slot' => max(0, $filledSlot),
        ];

        return $this->call(
            'update-seat',
            '/update-seat',
            fn(string $token) => $this->http(30)->withToken($token)
                ->post($this->baseUrl . '/update-seat', $payload),
            $payload
        );
    }

    /**
     * POST /upload-itenary  (multipart: kode_paket + file)
     * $absolutePath = path file PDF di server ini.
     */
    public function uploadItinerary(string $kodePaket, string $absolutePath, ?string $filename = null): array
    {
        $ctx = ['kode_paket' => $kodePaket, 'file' => basename($absolutePath)];

        if (!is_file($absolutePath) || !is_readable($absolutePath)) {
            Log::error('[HalalGo] upload-itenary: file tidak ditemukan', $ctx + ['path' => $absolutePath]);
            return $this->fail(0, 'File itinerary tidak ditemukan');
        }

        $contents = file_get_contents($absolutePath);
        $filename = $filename = $filename ?: 'itinerary.' . (pathinfo($absolutePath, PATHINFO_EXTENSION) ?: 'pdf');//$filename ?: 'itenary.pdf';

        return $this->call(
            'upload-itenary',
            '/upload-itenary',
            fn(string $token) => $this->http(120)->withToken($token)
                ->attach('file', $contents, $filename)
                ->post($this->baseUrl . '/upload-itenary', ['kode_paket' => $kodePaket]),
            $ctx
        );
    }

    // ================================================================
    // PIPELINE BERSAMA: token -> request -> (401 => token baru, ulangi sekali) -> parse
    // ================================================================

    private function call(string $label, string $path, \Closure $request, array $ctx = []): array
    {
        $url = $this->baseUrl . $path;

        $token = $this->getToken();
        if (!$token) {
            Log::error("[HalalGo] {$label}: dibatalkan, token tidak didapat", $ctx);
            return $this->fail(0, 'Gagal mendapatkan token');
        }

        $start = microtime(true);

        try {
            $res = $request($token);

            // Token bisa habis sebelum waktunya: minta token baru sekali lalu ulangi
            if ($res->status() === 401) {
                Log::warning("[HalalGo] {$label}: 401, token diperbarui lalu dicoba ulang", $ctx);

                $token = $this->getToken(true);
                if (!$token) {
                    Log::error("[HalalGo] {$label}: gagal memperbarui token", $ctx);
                    return $this->fail(401, 'Gagal memperbarui token');
                }
                $res = $request($token);
            }
        } catch (ConnectionException $e) {
            Log::error("[HalalGo] {$label}: koneksi gagal", array_merge($ctx, [
                'url' => $url,
                'duration_ms' => $this->elapsed($start),
                'error' => $e->getMessage(),
            ]));
            return $this->fail(0, 'Koneksi ke Halal Go gagal');
        }

        $body = $res->json() ?? [];
        $duration = $this->elapsed($start);

        // Dokumen: create/update-package => 201. Terima 200 juga untuk endpoint lain.
        if (in_array($res->status(), [200, 201], true) && data_get($body, 'status') === true) {
            $result = [
                'ok' => true,
                'http_status' => $res->status(),
                // upload-itenary mengembalikan data: [] -> pakai kode dari request
                'kode_paket' => data_get($body, 'data.kode_paket', $ctx['kode_paket'] ?? null),
                'status_ghg' => data_get($body, 'data.status_ghg'),
                'message' => data_get($body, 'message'),
            ];

            // status_ghg.status = false artinya relay Mitra -> GoHalalGo bermasalah,
            // walau Mitra sendiri sukses. Tidak menggagalkan proses, tapi dicatat.
            if (data_get($body, 'data.status_ghg.status') === false) {
                Log::warning("[HalalGo] {$label}: sukses di mitra, tapi status_ghg=false", array_merge($ctx, [
                    'status_ghg' => $this->snippet(data_get($body, 'data.status_ghg')),
                ]));
            }

            Log::info("[HalalGo] {$label}: sukses", array_merge($ctx, $result, ['duration_ms' => $duration]));
            return $result;
        }

        $errors = data_get($body, 'data.errors', []);

        $result = [
            'ok' => false,
            'http_status' => $res->status(),
            'message' => data_get($body, 'message', 'Respon tidak dikenali'),
            'errors' => is_array($errors) ? $errors : [(string) $errors],
        ];

        Log::error("[HalalGo] {$label}: gagal", array_merge($ctx, $result, [
            'url' => $url,
            'duration_ms' => $duration,
            'body' => $this->snippet($res->body()),
        ]));

        return $result;
    }

    // ================================================================
    // PAYLOAD
    // ================================================================

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
            // TODO produksi: ganti dua URL dummy ini dengan $paket->imgpaketumroh / $paket->itinerary
            // (harus URL publik; http://127.0.0.1 tidak bisa diakses server Halal Go)
            //'https://drive.google.com/uc?export=download&id=1YPxvOLIIW9ebPx1OLqC-R3GGZA24F4Yt',//
            //'https://pdfobject.com/pdf/sample.pdf',//
            'imgpaketumroh' => $paket->imgpaketumroh ?? '',
            'itinerary' => $paket->itinerary ?? '',
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