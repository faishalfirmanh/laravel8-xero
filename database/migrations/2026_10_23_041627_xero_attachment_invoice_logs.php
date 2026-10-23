<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class XeroAttachmentInvoiceLogs extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('xero_attachment_invoice_logs', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id', 100);
            $table->string('invoice_id', 100);
            $table->string('invoice_number', 100)->nullable();
            $table->date('invoice_date')->nullable();
            $table->string('status', 20); // done, failed
            $table->unsignedInteger('total_file')->default(0);
            $table->unsignedBigInteger('export_id')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'invoice_id']);
            $table->index(['tenant_id', 'status', 'invoice_date']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}
