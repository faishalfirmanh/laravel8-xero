<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBankXerosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('bank_xeros', function (Blueprint $table) {
            $table->id();
            $table->string('account_id');
            $table->string('code');
             $table->string('name');
            $table->smallInteger('status');
            $table->string('type');
            $table->string('currency_code');
            $table->string('account_number');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('bank_xeros');
    }
}
