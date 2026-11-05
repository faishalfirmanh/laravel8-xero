<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateXeroSyncCursors extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('xero_sync_cursors', function (Blueprint $table) {
            $table->id();
            $table->string('scope')->unique();            // 'ALL' atau bulan 'm-Y' (mis. 02-2026)
            $table->string('phase')->default('invoices'); // invoices | payments | done
            $table->date('last_invoice_date')->nullable();
            $table->date('last_payment_date')->nullable();
            $table->string('last_job_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('xero_sync_cursors');
    }
}
