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
        User::updateOrCreate(
            ['email' => 'staff2@stardust.com'],
            [
                'name' => 'Staff Gudang Jakarta',
                'password' => Hash::make('password'),
                'role' => 'user',
                'id_warehouse' => 2,
            ]
        );

        User::updateOrCreate(
            ['email' => 'staff3@stardust.com'],
            [
                'name' => 'Staff Gudang Semarang',
                'password' => Hash::make('password'),
                'role' => 'user',
                'id_warehouse' => 3,
            ]
        );
        User::updateOrCreate(
            ['email' => 'staff4@stardust.com'],
            [
                'name' => 'Staff Gudang Bandung',
                'password' => Hash::make('password'),
                'role' => 'user',
                'id_warehouse' => 4,
            ]
        );
        User::updateOrCreate(
            ['email' => 'staff5@stardust.com'],
            [
                'name' => 'Staff Gudang Surabaya',
                'password' => Hash::make('password'),
                'role' => 'user',
                'id_warehouse' => 5,
            ]
        );
    }
}
