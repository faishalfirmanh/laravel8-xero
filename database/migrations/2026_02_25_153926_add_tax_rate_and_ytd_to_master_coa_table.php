<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('master_coa', function (Blueprint $table) {
            $table->decimal('tax_rate', 10, 2)->nullable()->after('tax_type');
            $table->decimal('ytd', 15, 2)->nullable()->after('tax_rate');
        });
    }

    public function down(): void
    {
        Schema::table('master_coa', function (Blueprint $table) {
            $table->dropColumn(['tax_rate', 'ytd']);
        });
    }
};