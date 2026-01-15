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

        // 1. Cek Limit Harian
        if (RateLimiter::tooManyAttempts($keyDay, self::LIMIT_DAY)) {
            $seconds = RateLimiter::availableIn($keyDay);
            throw new Exception("Xero Daily Limit Reached. Try again in " . gmdate("H:i:s", $seconds));
        }

        // 2. Cek Limit Menit (Paling sering kena ini)
        if (RateLimiter::tooManyAttempts($keyMinute, self::LIMIT_MINUTE)) {
            $seconds = RateLimiter::availableIn($keyMinute);
            throw new Exception("Xero Minute Limit Reached. Slow down! Wait {$seconds} seconds.");
        }

        // 3. Jika aman, catat request ini (Hit)
        // Parameter kedua adalah waktu decay dalam detik (60 detik & 86400 detik/24 jam)
        RateLimiter::hit($keyMinute, 60);
        RateLimiter::hit($keyDay, 86400);

        return true;
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
