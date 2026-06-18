<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnInvIdOnLog extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('log_histories', function (Blueprint $table) {
            $table->integer('salles_inv_id')->nullable();
            $table->integer('bills_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('log_histories', function (Blueprint $table) {
            $table->string('salles_inv_id')->nullable()->change();
            $table->string('bills_id')->nullable()->change();
        });
    }
}
