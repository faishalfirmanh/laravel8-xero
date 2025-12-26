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
        Schema::create('config_setting_xeros', function (Blueprint $table) {
            $table->id();
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->text('xero_tenant_id')->nullable();
            $table->text('barer_token')->nullable();
            $table->string('id_token')->nullable();
            //
            $table->string('client_id')->nullable();
            $table->string('client_secret')->nullable();
            $table->string('code')->nullable();
            $table->string('redirect_url')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('config_setting_xeros');
    }
};
