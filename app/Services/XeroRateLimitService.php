<?php

namespace App\Services;

use Illuminate\Support\Facades\RateLimiter;
use Exception;

class XeroRateLimitService
{
    // Batasan Xero (Standar)
    // Minute Limit: 60 request per menit (rolling)
    // Daily Limit: 5000 request per hari
    const LIMIT_MINUTE = 60;
    const LIMIT_DAY = 5000;

    /**
     * Cek apakah boleh request ke Xero.
     * Jika limit habis, akan throw Exception.
     * Jika aman, akan mencatat (hit) request ini.
     * * @param string $tenantId ID Tenant Xero (karena limit dihitung per tenant)
     */
   public function checkAndHit(string $tenantId)
    {
        $keyMinute = "xero_limit:min:{$tenantId}";
        $keyDay    = "xero_limit:day:{$tenantId}";

        // 1. Cek Limit Harian (Jika habis, baru Throw Exception karena nunggu besok kelamaan)
        if (RateLimiter::tooManyAttempts($keyDay, self::LIMIT_DAY)) {
             // Opsional: Sleep jika sisa waktu < 5 menit, tapi biasanya daily limit = stop hari ini.
             throw new Exception("Daily Limit Reached.");
        }

        // 2. Cek Limit Menit (BLOCKING / SLEEP)
        if (RateLimiter::tooManyAttempts($keyMinute, self::LIMIT_MINUTE)) {
            $seconds = RateLimiter::availableIn($keyMinute) + 1; // Tambah 1 detik buffer

            Log::warning("Local Limit Reached. Sleeping {$seconds}s...");
            sleep($seconds); // <--- PERBAIKAN UTAMA: TUNGGU, JANGAN ERROR
        }

        // 3. Catat Hit
        RateLimiter::hit($keyMinute, 60);
        RateLimiter::hit($keyDay, 86400);
    }

    /**
     * Mengambil info sisa limit (Untuk ditampilkan di JSON/Debug)
     */
    public function getUsageInfo(string $tenantId)
    {
        $keyMinute = "xero_limit:min:{$tenantId}";
        $keyDay    = "xero_limit:day:{$tenantId}";

        return [
            'minute' => [
                'used' => RateLimiter::attempts($keyMinute),
                'remaining' => RateLimiter::remaining($keyMinute, self::LIMIT_MINUTE),
                'limit' => self::LIMIT_MINUTE
            ],
            'day' => [
                'used' => RateLimiter::attempts($keyDay),
                'remaining' => RateLimiter::remaining($keyDay, self::LIMIT_DAY),
                'limit' => self::LIMIT_DAY
            ]
        ];
    }
}
