<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnPriceOnPaket extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {

        if (!Schema::hasColumn('paket_umrohs', 'price')) {
            Schema::table('paket_umrohs', function (Blueprint $table) {
                $table->decimal('price', 10, 4)->nullable();
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
        Schema::table('paket_umrohs', function (Blueprint $table) {
            $table->dropColumn('price');
        });
    }
}
