<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class DropSpendingAmountInvoiceAllFromXero extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('invoices_all_from_xeros', function (Blueprint $table) {
            if (Schema::hasColumn('invoices_all_from_xeros', 'spending_amount')) {
                Schema::table('invoices_all_from_xeros', function (Blueprint $table) {
                    $table->dropColumn('spending_amount');
                });
            }

            if (Schema::hasColumn('invoices_all_from_xeros', 'spending_amount')) {
                Schema::table('invoices_all_from_xeros', function (Blueprint $table) {
                    $table->dropColumn('spending_amount');
                });
            }

            if (Schema::hasColumn('invoices_all_from_xeros', 'profit_amount')) {
                Schema::table('invoices_all_from_xeros', function (Blueprint $table) {
                    $table->dropColumn('profit_amount');
                });
            }

            if (!Schema::hasColumn('invoices_all_from_xeros', 'uuid_proudct_and_service')) {
                Schema::table('invoices_all_from_xeros', function (Blueprint $table) {
                    //$table->dropColumn('profit_amount');
                    $table->string('uuid_proudct_and_service')->nullable();//paket
                });
            }
            $table->decimal('invoice_amount', 27, 2)->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('invoices_all_from_xeros', function (Blueprint $table) {
            $table->dropColumn('spending_amount');
            $table->dropColumn('profit_amount');
        });
    }
}
