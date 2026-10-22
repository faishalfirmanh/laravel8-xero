<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnFasilitasTambahan extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasColumn('paket_umrohs', 'fasilitas_tambahan')) {
            Schema::table('paket_umrohs', function (Blueprint $table) {
                $table->text('fasilitas_tambahan')->nullable();
            });
        }

        if (!Schema::hasColumn('paket_umrohs', 'is_internal')) {
            Schema::table('paket_umrohs', function (Blueprint $table) {
                $table->boolean('is_internal')->default(true);
            });
        }

        if (!Schema::hasColumn('paket_umrohs', 'is_posting')) {
            Schema::table('paket_umrohs', function (Blueprint $table) {
                $table->boolean('is_posting')->default(true);
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
        Schema::table('paket_umrohs', function (Blueprint $table) {
            //
        });
    }
}
