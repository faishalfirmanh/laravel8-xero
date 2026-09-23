<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
class AddColummodifiedAfterOnSyncJob extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('sync_job_statuses', function (Blueprint $table) {
            $table->dateTime('modified_after')
                ->nullable()
                ->after('current_phase')
                ->comment('ModifiedAfter yang dipakai saat job run/resume. Null = full sync.');

            // OPSI #1 — diisi saat phase berhasil = done
            // Jadi acuan ModifiedAfter run berikutnya
            $table->dateTime('last_completed_at')
                ->nullable()
                ->after('modified_after')
                ->comment('Timestamp job selesai penuh (done). Jadi ModifiedAfter run berikutnya.');
        });
        DB::statement("
            UPDATE sync_job_statuses
            SET last_completed_at = updated_at
            WHERE current_phase = 'done'
              AND last_completed_at IS NULL
        ");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('sync_job_statuses', function (Blueprint $table) {
            Schema::table('sync_job_statuses', function (Blueprint $table) {
                $table->dropColumn(['modified_after', 'last_completed_at']);
            });
        });
    }
}
