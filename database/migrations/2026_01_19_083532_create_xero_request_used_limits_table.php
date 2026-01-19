<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateXeroRequestUsedLimitsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('xero_request_used_limits', function (Blueprint $table) {
            $table->id();
            $table->integer('total_request_used_min')->default(0);
            $table->integer('total_request_used_day')->default(0);
            $table->integer('available_request_min')->default(0);
            $table->integer('available_request_day')->default(0);
            $table->date('tracking_date');
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
        Schema::dropIfExists('xero_request_used_limits');
    }
}
