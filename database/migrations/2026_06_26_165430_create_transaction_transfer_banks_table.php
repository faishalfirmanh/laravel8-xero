<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTransactionTransferBanksTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('transaction_transfer_banks')) {
            Schema::create('transaction_transfer_banks', function (Blueprint $table) {
                $table->id();

                $table->foreignId('bank_id_from')
                    ->constrained('bank_xeros')
                    ->cascadeOnDelete();


                $table->foreignId('bank_id_to')
                    ->constrained('bank_xeros')
                    ->cascadeOnDelete();


                $table->date('date_trans');
                $table->decimal('amount', 19, 4)->default(0);
                $table->text('reference_transfer_bank');
                $table->text('code_tracking_paket_from')->nullable();
                $table->text('code_tracking_divisi_from')->nullable();
                $table->text('code_tracking_paket_to')->nullable();
                $table->text('code_tracking_divisi_to')->nullable();

                $table->timestamps();
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
        Schema::dropIfExists('transaction_transfer_banks');
    }
}
