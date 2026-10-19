<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumKetOnmCurrency extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasColumn('m_currency', 'ket')) {
            Schema::table('m_currency', function (Blueprint $table) {
                $table->string('ket')->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('m_currency', function (Blueprint $table) {
            $table->dropColumn('ket');
        });
    }
}
