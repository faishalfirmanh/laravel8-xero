<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DuplicateUUIDController extends Controller
{
    private const MAX_ATTEMPTS = 3;
    private const RETRYABLE_ERRORS = [2006, 2013]; // gone away, lost connection

    public function duplicateJoinRows()
    {
        $sql = "
            SELECT tac.id, tac.uuid_detail, COUNT(*) AS jumlah_baris_join
            FROM transaction_all_coas tac
            LEFT JOIN item_detail_invoices idi ON tac.uuid_detail = idi.uuid_detail_inv
            JOIN invoices_all_from_xeros iafx ON idi.parent_inv_id = iafx.id
            LEFT JOIN d_bills db ON tac.uuid_detail = db.uuid_detail
            JOIN p_bills pb ON db.bills_parent_id = pb.id
            GROUP BY tac.id, tac.uuid_detail
            HAVING COUNT(*) > 1
        ";

        $startedAt = microtime(true);
        $attempt = 0;

        while (true) {
            $attempt++;

            try {
                $this->prepareSession();

                $rows = DB::select($sql);

                return response()->json([
                    'success' => true,
                    'total' => count($rows),
                    'attempts' => $attempt,
                    'duration_ms' => round((microtime(true) - $startedAt) * 1000),
                    'data' => $rows,
                ]);
            } catch (QueryException $e) {
                $code = isset($e->errorInfo[1]) ? (int) $e->errorInfo[1] : 0;
                $retryable = in_array($code, self::RETRYABLE_ERRORS, true);

                Log::warning('Duplicate uuid_detail query gagal', [
                    'attempt' => $attempt,
                    'code' => $code,
                    'message' => $e->getMessage(),
                ]);

                if ($retryable && $attempt < self::MAX_ATTEMPTS) {
                    // Buang koneksi lama dan buka koneksi baru, lalu ulangi query
                    DB::purge();
                    DB::reconnect();
                    usleep(500000); // jeda 0,5 detik sebelum retry
                    continue;
                }

                return response()->json([
                    'success' => false,
                    'message' => 'Query gagal dijalankan.',
                    'code' => $code,
                    'attempt' => $attempt,
                    'error' => config('app.debug') ? $e->getMessage() : null,
                ], 500);
            }
        }
    }

    /**
     * Perpanjang timeout sesi MySQL untuk query yang berjalan lama.
     * Dibungkus try agar tidak menghentikan request jika user tidak punya hak SET SESSION.
     */
    private function prepareSession()
    {
        try {
            DB::statement('SET SESSION net_read_timeout = 600');
            DB::statement('SET SESSION net_write_timeout = 600');
            DB::statement('SET SESSION wait_timeout = 600');
        } catch (\Throwable $e) {
            Log::notice('Tidak bisa mengatur timeout sesi MySQL: ' . $e->getMessage());
        }
    }
}