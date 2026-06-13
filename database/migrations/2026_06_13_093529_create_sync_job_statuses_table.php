<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSyncJobStatusesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('sync_job_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('job_id')->unique();          // UUID tracking
            $table->string('job_type');                  // nama job
            $table->enum('status', ['pending', 'running', 'success', 'failed'])->default('pending');
            $table->integer('total_synced')->default(0);
            $table->integer('total_pages')->default(0);
            $table->integer('current_page')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('sync_job_statuses');
    }
}
