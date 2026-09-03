<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ChangeReferenceNullableInPBillsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('p_bills', function (Blueprint $table) {
            Schema::table('p_bills', function (Blueprint $table) {
                $table->string('reference')->nullable()->change();
            });
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('p_bills', function (Blueprint $table) {
            Schema::table('p_bills', function (Blueprint $table) {
                $table->string('reference')->nullable(false)->change();
            });
        });
    }
}
