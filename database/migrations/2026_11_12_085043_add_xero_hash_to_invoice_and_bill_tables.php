<?php

use App\Models\Expenses\Purchase\Bill\PBill;
use App\Models\InvoicesAllFromXero;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddXeroHashToInvoiceAndBillTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $invoiceTable = (new InvoicesAllFromXero)->getTable();
        $billTable = (new PBill)->getTable();

        if (!Schema::hasColumn($invoiceTable, 'xero_hash')) {
            Schema::table($invoiceTable, function (Blueprint $table) {
                $table->string('xero_hash', 40)->nullable();
            });
        }

        if (!Schema::hasColumn($billTable, 'xero_hash')) {
            Schema::table($billTable, function (Blueprint $table) {
                $table->string('xero_hash', 40)->nullable();
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
        $invoiceTable = (new InvoicesAllFromXero)->getTable();
        $billTable = (new PBill)->getTable();

        if (Schema::hasColumn($invoiceTable, 'xero_hash')) {
            Schema::table($invoiceTable, function (Blueprint $table) {
                $table->dropColumn('xero_hash');
            });
        }

        if (Schema::hasColumn($billTable, 'xero_hash')) {
            Schema::table($billTable, function (Blueprint $table) {
                $table->dropColumn('xero_hash');
            });
        }

    }
}
