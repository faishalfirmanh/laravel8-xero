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
            $this->command->error('File JSON tidak ditemukan: ' . $path);
            return;
        }

        $jsonContent = file_get_contents($path);
        $users = json_decode($jsonContent, true);

        // Cek apakah JSON valid
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->command->error('JSON tidak valid: ' . json_last_error_msg());
            return;
        }

        $a = 0;
        foreach ($users as $value) {
            if (empty($value['email']) || empty($value['name'])) {
                $this->command->warn('Data tidak lengkap, dilewati.');
                continue;
            }

            if (User::where('email', $value['email'])->exists()) {
                $this->command->info("User {$value['email']} sudah ada, dilewati.");
                continue;
            }

            User::create([
                'name'     => $value['name'],
                'email'    => $value['email'],
                'password' => Hash::make($value['password'] ?? 'password123'),
            ]);

            $this->command->info('Sukses seeder: ' . $value['email']);
            $a++;
        }

        $this->command->info("Total user berhasil di-seed: {$a}");
    }   


}
