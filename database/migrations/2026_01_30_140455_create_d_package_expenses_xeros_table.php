<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDPackageExpensesXerosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('d_package_expenses_xeros', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('package_expenses_id');
            $table->unsignedBigInteger('pengeluaran_id');
            $table->decimal('nominal_idr',19, 4)->default(0);
            $table->decimal('nominal_sar',19, 4)->default(0);
            $table->boolean('is_idr')->default(true);
            $table->decimal('nominal_currency',19, 4)->default(0);//after  convert, kala
            $table->timestamps();
            $table->foreign('package_expenses_id')//
                ->references('id')
                ->on('package_expenses_xeros')
                ->onDelete('cascade');
            $table->foreign('pengeluaran_id')
                ->references('id')
                ->on('master_pengeluaran_pakets')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('d_package_expenses_xeros');
    }
}
