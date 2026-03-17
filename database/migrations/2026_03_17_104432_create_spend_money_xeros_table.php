<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSpendMoneyXerosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('spend_money_xeros', function (Blueprint $table) {
            $table->id();
  $table->string('to'); // penerima
            $table->date('date'); // tanggal transaksi
            $table->string('reference')->nullable(); // referensi

            $table->string('currency_code', 10)->default('IDR'); // mata uang

            $table->json('lines')->nullable(); // detail item (JSON array)

            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('tax', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);

            $table->tinyInteger('status')->default(1);
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
        Schema::dropIfExists('spend_money_xeros');
    }
}
