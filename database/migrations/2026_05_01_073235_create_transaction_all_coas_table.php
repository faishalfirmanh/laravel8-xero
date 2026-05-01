<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTransactionAllCoasTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('transaction_all_coas', function (Blueprint $table) {
            $table->id();
            $table->date('date_transaction');
            $table->string('uuid_coa');
            $table->string('reference');
            $table->boolean('is_speend');//true -> dikurangi, false ditambah = pemasukan
            $table->decimal('nominal', 19, 4)->default(0);
            $table->integer('created_by')->nullable();
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
        Schema::dropIfExists('transaction_all_coas');
    }
}
