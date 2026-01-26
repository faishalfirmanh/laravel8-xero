<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
class UserRegisterSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $path = base_path('user_register.json');

        if (!file_exists($path)) {
            return response()->json(['error' => 'File JSON tidak ditemukan'], 404);
        }else{
            $users = json_decode(file_get_contents($path), true);
            $a = 0;
            foreach ($users as $value) {

                if (User::where('email', $value['email'])->exists()) {
                    continue;
                }

                User::create([
                    'name'     => $value['name'],
                    'email'    => $value['email'],
                    'password' => Hash::make($value['password']),
                ]);
                $this->command->info('sukses seeder json register  ke '.$a);
                $a++;
            }
        }

    }
}
