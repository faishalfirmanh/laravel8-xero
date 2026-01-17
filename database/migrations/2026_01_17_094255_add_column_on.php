<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnOn extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('invoices_all_from_xeros', function (Blueprint $table) {
            $table->decimal('invoice_total',19, 4)->default(0);
            $table->date('issue_date')->nullable();
            $table->date('due_date')->nullable();
            $table->string('status')->nullable();
            $table->string('uuid_contact')->nullable();
            $table->string('contact_name')->nullable();
            $table->string('item_name')->nullable();
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
            //
        });
    }
}
