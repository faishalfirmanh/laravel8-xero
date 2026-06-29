<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSummaryNominalBanksTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('summary_nominal_banks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_id')
                ->constrained('bank_xeros')
                ->cascadeOnDelete();

            $table->decimal('nominal_in', 19, 4)->default(0);
            $table->decimal('nominal_out', 19, 4)->default(0);
            $table->decimal('final_nominal', 19, 4)->default(0);
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
        Schema::dropIfExists('summary_nominal_banks');
    }
}
