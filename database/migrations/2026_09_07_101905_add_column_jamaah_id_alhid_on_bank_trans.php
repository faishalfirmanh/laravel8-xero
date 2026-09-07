<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnJamaahIdAlhidOnBankTrans extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('transaction_bank_trans_p_s', function (Blueprint $table) {
            $table->unsignedBigInteger('id_jamaah_alhid')
                ->nullable()
                ->after('uuid_to');
            $table->index('id_jamaah_alhid');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('transaction_bank_trans_p_s', function (Blueprint $table) {
            $table->dropColumn('id_jamaah_alhid');
        });
    }
}
