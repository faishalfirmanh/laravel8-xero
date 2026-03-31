<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnBusinessLineId extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('master_role_users', function (Blueprint $table) {
            //
            $table->unsignedBigInteger('busines_line_id')->nullable();
            $table->foreign('busines_line_id')->references('id')->on('business_lines')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('master_role_users', function (Blueprint $table) {
            $table->dropColumn('busines_line_id');
        });
    }
}
