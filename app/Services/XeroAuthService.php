<?php

namespace App\Services;

use App\Models\ConfigSettingXero;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache; // Wajib untuk Locking
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class XeroAuthService
{
    public function getToken()
    {
        // 1. Ambil Config (Gunakan first, tapi cek jika kosong)
        $config = ConfigSettingXero::first();

        // JIKA TABEL KOSONG: User wajib login manual pertama kali via browser (/xero/connect)
        if (!$config) {
            throw new \Exception("Tabel ConfigSettingXero kosong. Silakan lakukan Login Xero (Initial Setup) terlebih dahulu.");
        }

        // 2. Cek Expiry (Buffer 5 menit sebelum expired)
        if (Carbon::now()->addMinutes(5)->greaterThanOrEqualTo($config->expires_at)) {
            // Gunakan Lock agar tidak bentrok antar user
            return $this->refreshTokenWithLock($config->id);
        }

        return $config->access_token;
    }

    /**
     * Menggunakan Atomic Lock untuk mencegah 'Race Condition'
     * Agar tidak ada 2 request yang me-refresh token bersamaan.
     */
    private function refreshTokenWithLock($configId)
    {
        // Kunci proses ini selama 10 detik dengan nama 'xero_refresh_lock'
        // block(5) artinya proses lain akan menunggu maksimal 5 detik
        return Cache::lock('xero_refresh_lock', 10)->block(5, function () use ($configId) {

            // 1. Ambil ulang data terbaru dari DB (PENTING!)
            // Siapa tahu proses lain barusan selesai me-refresh tokennya saat kita mengantre
            $config = ConfigSettingXero::find($configId);

            // Cek lagi, apakah sekarang tokennya sudah segar? (Hasil kerja proses lain)
            if (Carbon::now()->addMinutes(5)->lessThan($config->expires_at)) {
                // Token sudah diperbarui orang lain, kita pakai saja. Hemat request.
                return $config->access_token;
            }

            // 2. Jika masih expired, barulah KITA yang refresh ke Xero
            return $this->executeRefresh($config);
        });
    }

    private function executeRefresh($config)
    {
        try {
            Log::info("XeroAuthService: Memulai refresh token ke API Xero...");

            $response = Http::asForm()
                ->withBasicAuth(env('XERO_CLIENT_ID'), env('XERO_CLIENT_SECRET'))
                ->post('https://identity.xero.com/connect/token', [
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $config->refresh_token,
                ]);

            if ($response->failed()) {
                $errorData = $response->json();

                // Deteksi Invalid Grant (Token Hangus)
                if (isset($errorData['error']) && $errorData['error'] === 'invalid_grant') {
                    Log::critical("XERO CRITICAL: Invalid Grant. Token hangus. Wajib login ulang manual.");
                    throw new \Exception("Token Xero Hangus (Invalid Grant). Harap Login Ulang via Browser.");
                }

                throw new \Exception("Gagal Refresh Token Xero: " . $response->body());
            }

            $newTokens = $response->json();

            // 3. Update Database
            $config->update([
                'access_token'  => $newTokens['access_token'],
                'barer_token'   => $newTokens['access_token'], // Sesuaikan nama kolom Anda
                'refresh_token' => $newTokens['refresh_token'], // ROTASI TOKEN (Penting)
                'id_token'      => $newTokens['id_token'] ?? $config->id_token,
                'expires_at'    => Carbon::now()->addSeconds($newTokens['expires_in']),
            ]);

            Log::info("XeroAuthService: Refresh Berhasil. Next Expired: " . Carbon::now()->addSeconds($newTokens['expires_in']));

            return $newTokens['access_token'];

        } catch (\Exception $e) {
            Log::error('Xero Token Refresh Error: ' . $e->getMessage());
            throw $e;
        }
    }
}
