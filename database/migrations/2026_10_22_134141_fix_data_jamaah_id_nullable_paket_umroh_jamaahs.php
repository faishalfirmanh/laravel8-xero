<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class FixDataJamaahIdNullablePaketUmrohJamaahs extends Migration
{
    public function up()
    {
        // Ambil foreign key yang benar-benar ada di database
        $foreignKeys = DB::select("
            SELECT CONSTRAINT_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'paket_umroh_jamaahs'
              AND COLUMN_NAME = 'data_jamaah_id'
              AND REFERENCED_TABLE_NAME IS NOT NULL
        ");

        foreach ($foreignKeys as $foreignKey) {
            $constraint = str_replace('`', '``', $foreignKey->CONSTRAINT_NAME);

            DB::statement(
                "ALTER TABLE `paket_umroh_jamaahs` DROP FOREIGN KEY `{$constraint}`"
            );
        }

        // Jadikan nullable tanpa menghapus data
        DB::statement("
            ALTER TABLE `paket_umroh_jamaahs`
            MODIFY `data_jamaah_id` BIGINT UNSIGNED NULL
        ");
    }

    public function down()
    {
        // Rollback: kolom kembali NOT NULL.
        // Akan gagal jika masih ada baris dengan data_jamaah_id NULL.
        DB::statement("
            ALTER TABLE `paket_umroh_jamaahs`
            MODIFY `data_jamaah_id` BIGINT UNSIGNED NOT NULL
        ");
    }
}
