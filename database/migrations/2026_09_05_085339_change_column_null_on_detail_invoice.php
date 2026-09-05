<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ChangeColumnNullOnDetailInvoice extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('item_detail_invoices', function (Blueprint $table) {
            $table->string('uuid_item')->nullable()->change();

            $table->integer('qty')->nullable()->default(0)->change();

            $table->decimal('unit_price', 19, 4)
                ->nullable()
                ->default(0)
                ->change();

            $table->decimal('total_amount_each_row', 19, 4)
                ->nullable()
                ->default(0)
                ->change();
            $table->string('line_item_uuid')->nullable()->change();

            // coa_id TIDAK diubah, tetap nullable
            $table->unsignedBigInteger('parent_inv_id')->nullable()->change();

            $table->unsignedBigInteger('item_id')->nullable()->change();

            $table->unsignedSmallInteger('sort_order')
                ->nullable()
                ->default(0)
                ->change();

            $table->text('uuid_detail_inv')->nullable()->change();

            $table->string('paket_tracking_uuid')->nullable()->change();

            $table->string('divisi_travel_tracking_uuid')->nullable()->change();

            // desc TIDAK diubah, tetap nullable
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('item_detail_invoices', function (Blueprint $table) {
            $table->string('uuid_item')->nullable(false)->change();

            $table->integer('qty')->nullable(false)->default(0)->change();

            $table->decimal('unit_price', 19, 4)
                ->nullable(false)
                ->default(0)
                ->change();

            $table->decimal('total_amount_each_row', 19, 4)
                ->nullable(false)
                ->default(0)
                ->change();
            $table->string('line_item_uuid')->nullable()->change();

            $table->unsignedBigInteger('parent_inv_id')->nullable()->change();

            $table->unsignedBigInteger('item_id')->nullable()->change();

            $table->unsignedSmallInteger('sort_order')
                ->nullable(false)
                ->default(0)
                ->change();

            $table->text('uuid_detail_inv')->nullable()->change();

            $table->string('paket_tracking_uuid')->nullable()->change();

            $table->string('divisi_travel_tracking_uuid')->nullable()->change();
        });

    }
}
