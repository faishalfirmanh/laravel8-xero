<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnIdItem extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasColumn('d_bills', 'item_id_prod_serv')) {
            Schema::table('d_bills', function (Blueprint $table) {
                $table->unsignedBigInteger('item_id_prod_serv')->nullable();
                $table->foreign('item_id_prod_serv')
                    ->references('id')
                    ->on('items_paket_all_from_xeros')
                    ->onDelete('cascade');
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
        Schema::table('d_bills', function (Blueprint $table) {
            $table->dropColumn('item_id_prod_serv');
        });
    }
}
