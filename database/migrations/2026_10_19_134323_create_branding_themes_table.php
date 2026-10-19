<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBrandingThemesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('branding_themes', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('company_name', 191)->nullable();
            $table->text('address')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('email', 100)->nullable();
            $table->string('logo_path')->nullable();
            $table->string('primary_color', 7)->default('#3d76f1');
            $table->text('terms')->nullable();
            $table->string('signature_name')->nullable();
            $table->string('signature_title')->nullable();
            $table->boolean('is_default')->default(false);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::table('invoices_all_from_xeros', function (Blueprint $table) {
            $table->unsignedBigInteger('branding_theme_id')->nullable()->after('reference');
            $table->index('branding_theme_id');
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
            $table->unsignedBigInteger('branding_theme_id')->nullable()->after('reference');
            $table->index('branding_theme_id');
        });

        Schema::dropIfExists('branding_themes');
    }
}
