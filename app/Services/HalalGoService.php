<?php

namespace App\Services;


use App\Models\GoHalal\PaketUmroh;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class HalalGoService
{
    const CACHE_KEY = 'halal_go_token';

    private string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.halal.base_url'), '/');
    }

    /**
     * Ambil token dari cache, atau minta token baru ke get-token.
     */
    public function getToken(bool $forceNew = false): ?string
    {
        if (!$forceNew && ($cached = Cache::get(self::CACHE_KEY))) {
            return $cached;
        }

        $res = Http::acceptJson()->timeout(30)->post($this->baseUrl . '/get-token', [
            'client_id' => config('services.halal.client_id'),
            'client_key' => config('services.halal.client_key'),
        ]);

        $body = $res->json();

        if (!in_array($res->status(), [200, 201], true) || !data_get($body, 'status')) {
            Log::error('[HalalGo] get-token gagal', [
                'http_status' => $res->status(),
                'body' => $res->body(),
            ]);
            return null;
        }

        $token = data_get($body, 'data.token');
        $expired = data_get($body, 'data.expired');

        // Simpan sampai 60 detik sebelum expired
        $ttl = $expired
            ? max(0, now()->diffInSeconds(Carbon::parse($expired), false) - 60)
            : 0;

        if ($token && $ttl > 0) {
            Cache::put(self::CACHE_KEY, $token, $ttl);
        }

        return $token;
    }

    /**
     * Kirim paket ke create-package.
     * Return: ['ok' => bool, 'http_status' => int, 'kode_paket' => ?, 'status_ghg' => ?, 'message' => ?]
     */
    public function createPackage(array $payload): array
    {
        $token = $this->getToken();
        if (!$token) {
            return ['ok' => false, 'http_status' => 0, 'message' => 'Gagal mendapatkan token'];
        }

        $res = $this->post('/create-package', $payload, $token);

        // Token bisa habis sebelum waktunya: minta token baru sekali lalu ulangi
        if ($res->status() === 401) {
            $token = $this->getToken(true);
            if (!$token) {
                return ['ok' => false, 'http_status' => 401, 'message' => 'Gagal memperbarui token'];
            }
            $res = $this->post('/create-package', $payload, $token);
        }

        $body = $res->json() ?? [];

        if ($res->status() === 201 && data_get($body, 'status') === true) {
            $result = [
                'ok' => true,
                'http_status' => 201,
                'kode_paket' => data_get($body, 'data.kode_paket'),
                'status_ghg' => data_get($body, 'data.status_ghg'),
                'message' => data_get($body, 'message'),
            ];

            Log::info('[HalalGo] create-package sukses', $result);
            return $result;
        }

        $result = [
            'ok' => false,
            'http_status' => $res->status(),
            'message' => data_get($body, 'message', 'Respon tidak dikenali'),
        ];

        Log::error('[HalalGo] create-package gagal', $result + ['body' => $res->body()]);

        return $result;
    }

    private function post(string $path, array $payload, string $token)
    {
        return Http::acceptJson()
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