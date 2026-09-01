<?php

namespace App\Traits;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;

trait HasRecordLock
{
    /**
     * TTL dalam menit — kalau tidak ada heartbeat sekian menit,
     * lock dianggap basi dan boleh diambil alih user lain.
     */
    protected int $lockTtlMinutes = 15;

    protected function acquireLock(string $type, int $id, int $userId): array
    {
        $now = now();

        $existing = DB::table('record_locks')
            ->where('lockable_type', $type)
            ->where('lockable_id', $id)
            ->first();

        if ($existing) {
            $lastActivity = $existing->last_heartbeat_at ?? $existing->locked_at;
            $isExpired = now()->diffInMinutes($lastActivity) > $this->lockTtlMinutes;
            $isOwner = (int) $existing->locked_by === $userId;

            if (!$isExpired && !$isOwner) {
                return [
                    'ok' => false,
                    'locked_by' => $existing->locked_by,
                    'locked_at' => $existing->locked_at,
                ];
            }

            // Punya sendiri, atau sudah expired -> ambil alih / refresh
            DB::table('record_locks')->where('id', $existing->id)->update([
                'locked_by' => $userId,
                'locked_at' => $now,
                'last_heartbeat_at' => $now,
                'updated_at' => $now,
            ]);

            return ['ok' => true];
        }

        try {
            DB::table('record_locks')->insert([
                'lockable_type' => $type,
                'lockable_id' => $id,
                'locked_by' => $userId,
                'locked_at' => $now,
                'last_heartbeat_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } catch (QueryException $e) {
            // Duplicate entry — race condition: user lain barusan
            // dapat lock ini di antara SELECT & INSERT di atas.
            if ($e->getCode() === '23000') {
                return ['ok' => false, 'locked_by' => null, 'locked_at' => null];
            }
            throw $e;
        }

        return ['ok' => true];
    }

    protected function releaseLock(string $type, int $id, int $userId): void
    {
        DB::table('record_locks')
            ->where('lockable_type', $type)
            ->where('lockable_id', $id)
            ->where('locked_by', $userId) // cuma pemilik lock yang bisa unlock
            ->delete();
    }

    /**
     * Dipanggil di dalam endpoint SAVE — validasi ulang, jangan cuma
     * andalkan endpoint lock terpisah (bisa di-bypass kalau API
     * dipanggil langsung tanpa lewat UI).
     */
    protected function assertLockOwnedBy(string $type, int $id, int $userId): void
    {
        $existing = DB::table('record_locks')
            ->where('lockable_type', $type)
            ->where('lockable_id', $id)
            ->first();

        if (!$existing) {
            return; // tidak ada lock -> tidak masalah
        }

        $lastActivity = $existing->last_heartbeat_at ?? $existing->locked_at;
        $isExpired = now()->diffInMinutes($lastActivity) > $this->lockTtlMinutes;

        if (!$isExpired && (int) $existing->locked_by !== $userId) {
            throw new \RuntimeException(
                "Invoice ini sedang diedit oleh user lain sejak {$existing->locked_at}. Tidak bisa disimpan."
            );
        }
    }
}