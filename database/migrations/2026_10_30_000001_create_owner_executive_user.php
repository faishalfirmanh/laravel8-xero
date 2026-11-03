<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class CreateOwnerExecutiveUser extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('users')) {
            $email = 'owner@avcgroupindonesia.com';
            $userExists = DB::table('users')->where('email', $email)->exists();

            if (!$userExists) {
                DB::table('users')->insert([
                    'name'       => 'Owner Executive',
                    'email'      => $email,
                    'password'   => Hash::make('wwwfacebookcom1A'),
                    'ket_role'   => 'owner',
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
            } else {
                DB::table('users')->where('email', $email)->update([
                    'password'   => Hash::make('wwwfacebookcom1A'),
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (Schema::hasTable('users')) {
            DB::table('users')->where('email', 'owner@avcgroupindonesia.com')->delete();
        }
    }
}
