<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnsDateDesc extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('payment_hotels', function (Blueprint $table) {
            $table->date('date_transfer')->nullable();
            $table->string('desc')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('payment_hotels', function (Blueprint $table) {
            Schema::dropIfExists('date_transfer');
            Schema::dropIfExists('desc');
        });
    }
}
