<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRecordLocksTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('record_locks', function (Blueprint $table) {
            $table->id();
            $table->string('lockable_type', 50);
            $table->unsignedBigInteger('lockable_id');
            $table->unsignedBigInteger('locked_by');
            $table->timestamp('locked_at');
            $table->timestamp('last_heartbeat_at')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamps();
            $table->unique(['lockable_type', 'lockable_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('record_locks');
    }
}
