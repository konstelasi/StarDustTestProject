<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@stardust.com'],
            [
                'name' => 'System Administrator',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'id_warehouse' => null,
            ]
        );

        User::updateOrCreate(
            ['email' => 'staff@stardust.com'],
            [
                'name' => 'Staff Gudang Utama',
                'password' => Hash::make('password'),
                'role' => 'user',
                'id_warehouse' => 1,
            ]
        );
    }
}
