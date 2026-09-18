<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnLastPageOnJob extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('sync_job_statuses', function (Blueprint $table) {

            if (!Schema::hasColumn('sync_job_statuses', 'last_page')) {
                $table->unsignedInteger('last_page')->default(0)->after('total_pages');
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
            $table->dropColumn(['last_page']);
        });
    }
}
