<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnContactId extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('spend_money_xeros', function (Blueprint $table) {
            //
             $table->tinyInteger('type_trans')->default(1);
             $table->integer('contact_id');
             $table->integer('bank_id');
             $table->tinyInteger('tax_type')->default(1);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('spend_money_xeros', function (Blueprint $table) {
            Schema::dropIfExists('type_trans');
            Schema::dropIfExists('contact_id');
            Schema::dropIfExists('bank_id');
            Schema::dropIfExists('tax_type');
        });
    }
}
