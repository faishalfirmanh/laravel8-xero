<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ChangeColumnAccountIdCoaDbill extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('d_bills', function (Blueprint $table) {
            $table->unsignedBigInteger('account_id_coa')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('d_bills', function (Blueprint $table) {
            $table->dropColumn('account_id_coa');
        });
    }
}
