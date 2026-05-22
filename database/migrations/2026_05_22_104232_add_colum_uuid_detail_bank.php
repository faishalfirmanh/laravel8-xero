<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumUuidDetailBank extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('transaction_bank_trans_d_s', function (Blueprint $table) {
            $table->string('uuid_detail_trans_bank', 10)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('transaction_bank_trans_d_s', function (Blueprint $table) {
            $table->dropColumn('uuid_detail_trans_bank');
        });
    }
}
