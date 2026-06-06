<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnIdParentInvOnTransBank extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasColumn('transaction_nominal_bank_accounts', 'id_parent_invoice')) {
            Schema::table('transaction_nominal_bank_accounts', function (Blueprint $table) {
                $table->unsignedInteger('id_parent_invoice')->nullable();
                $table->foreign('id_parent_invoice')
                    ->references('id')
                    ->on('invoices_all_from_xeros')
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
        Schema::table('transaction_nominal_bank_accounts', function (Blueprint $table) {
            $table->dropColumn('id_parent_inv');
        });
    }
}
