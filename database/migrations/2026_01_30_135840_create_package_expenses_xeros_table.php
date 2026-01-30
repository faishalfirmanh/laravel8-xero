<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePackageExpensesXerosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('package_expenses_xeros', function (Blueprint $table) {
            $table->id();
            $table->string('uuid_paket_item');
            $table->string('code_paket');
            $table->string('name_paket');
            $table->decimal('nominal_purchase',19, 4)->default(0);
            $table->decimal('nominal_sales',19, 4)->default(0);
            $table->decimal('nominal_profit',19, 4)->nullable();
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
        Schema::dropIfExists('package_expenses_xeros');
    }
}
