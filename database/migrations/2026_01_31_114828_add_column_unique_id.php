<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnUniqueId extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('d_package_expenses_xeros', function (Blueprint $table) {
            //
              $table->string('combine_id_random')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('d_package_expenses_xeros', function (Blueprint $table) {
            //
            Schema::dropIfExists('combine_id_random');
        });
    }
}
