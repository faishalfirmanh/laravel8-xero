<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payments_history_fixes', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number');
            $table->string('contact_name');
            $table->string('invoice_uuid');
            $table->string('payment_uuid');
            $table->date('date');
            $table->decimal('amount',19, 4);
            $table->string('reference');//fleg
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments_history_fixes');
    }
};
