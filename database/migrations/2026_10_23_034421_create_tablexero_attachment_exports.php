<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTablexeroAttachmentExports extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('xero_attachment_exports', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id', 100);
            $table->string('status', 20)->default('pending'); // pending, processing, done, failed
            $table->unsignedInteger('total_invoice')->default(0);
            $table->unsignedInteger('total_file')->default(0);
            $table->string('file_path')->nullable();
            $table->text('error')->nullable();
            $table->unsignedBigInteger('requested_by')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('xero_attachment_exports');
    }
}
