<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnParseh extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('sync_job_statuses', function (Blueprint $table) {
            if (!Schema::hasColumn('sync_job_statuses', 'current_phase')) {
                $table->string('current_phase', 20)->default('invoices')->after('total_pages');
            }
            if (!Schema::hasColumn('sync_job_statuses', 'total_pages_payment')) {
                $table->unsignedInteger('total_pages_payment')->default(0)->after('current_phase');
            }
            if (!Schema::hasColumn('sync_job_statuses', 'total_payment_synced')) {
                $table->unsignedInteger('total_payment_synced')->default(0)->after('total_pages_payment');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('sync_job_statuses', function (Blueprint $table) {
            $table->dropColumn(['current_phase', 'total_pages_payment', 'total_payment_synced']);
        });
    }
}
