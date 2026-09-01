<?php

namespace App\Http\Middleware;

use Closure;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LockRecord
{
    // Kalau tidak ada aktivitas (dibuka ulang / disimpan) sekian menit,
    // lock dianggap basi & boleh diambil user lain.
    protected int $ttlMinutes = 2;

    public function handle(Request $request, Closure $next, string $type)
    {

        $id = (int) $request->input('id'); // sesuaikan kalau nama param beda

        // Invoice baru (belum ada id) -> tidak perlu dikunci
        if (!$id) {
            return $next($request);
        }

        $userId = optional($request->user_login)->id;
        if (!$userId) {
            return $next($request); // biar auth middleware yang urus
        }

        $existing = DB::table('record_locks')
            ->where('lockable_type', $type)
            ->where('lockable_id', $id)
            ->first();

        $isExpired = $existing
            && Carbon::parse($existing->last_activity_at)->diffInMinutes(now()) > $this->ttlMinutes;

        if ($existing && !$isExpired && (int) $existing->locked_by !== $userId) {
            return response()->json([
                'success' => false,
                'message' => "Data ini sedang dibuka/diedit oleh user lain sejak {$existing->locked_at}.",
            ], 423); // 423 Locked
        }

        // Ambil / refresh lock milik user ini
        DB::table('record_locks')->updateOrInsert(
            ['lockable_type' => $type, 'lockable_id' => $id],
            [
                'locked_by' => $userId,
                'locked_at' => $existing->locked_at ?? now(),
                'last_activity_at' => now(),
                'updated_at' => now(),
                'created_at' => $existing->created_at ?? now(),
            ]
        );

        $response = $next($request);

        // Kalau ini request SIMPAN dan sukses -> langsung lepas lock
        if (in_array($request->method(), ['POST', 'PUT', 'PATCH']) && $response->getStatusCode() < 300) {
            DB::table('record_locks')
                ->where('lockable_type', $type)
                ->where('lockable_id', $id)
                ->where('locked_by', $userId)
                ->delete();
        }

        return $response;
    }
}