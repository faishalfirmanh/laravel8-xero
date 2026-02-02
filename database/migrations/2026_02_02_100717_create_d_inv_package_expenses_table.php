<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDInvPackageExpensesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('d_inv_package_expenses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('package_expenses_id');
            $table->unsignedBigInteger('invoices_xero_id');
            $table->decimal('amount_invoice',19, 4)->default(0);

            $table->foreign('package_expenses_id')
                ->references('id')
                ->on('package_expenses_xeros')
                ->onDelete('cascade');

            $table->foreign('invoices_xero_id')
                ->references('id')
                ->on('invoices_all_from_xeros')
                ->onDelete('cascade');
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
        Schema::dropIfExists('d_inv_package_expenses');
    }
}
