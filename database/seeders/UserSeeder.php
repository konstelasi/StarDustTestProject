<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
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
                'role' => User::ROLE_ADMIN_PLATFORM,
                'id_warehouse' => null,
            ]
        );

        User::updateOrCreate(
            ['email' => 'admingudang@stardust.com'],
            [
                'name' => 'Budi Hendra (Admin Gudang)',
                'password' => Hash::make('password'),
                'role' => User::ROLE_ADMIN_GUDANG,
                'id_warehouse' => 1,
                'company_id' => (int) config('stardust.tenant_id', 1),
            ]
        );

        $staffUsers = [
            [
                'name' => 'Ahmad Rozak',
                'email' => 'staff@stardust.com',
                'role' => User::ROLE_STAFF_GUDANG,
                'id_warehouse' => 1,
            ],
            [
                'name' => 'Rizky Haikal',
                'email' => 'staff2@stardust.com',
                'role' => User::ROLE_STAFF_GUDANG,
                'id_warehouse' => 2,
            ],
            [
                'name' => 'Farrel Daffa',
                'email' => 'staff3@stardust.com',
                'role' => User::ROLE_STAFF_GUDANG,
                'id_warehouse' => 1,
            ],
            [
                'name' => 'Putra Aditama',
                'email' => 'staff4@stardust.com',
                'role' => User::ROLE_STAFF_GUDANG,
                'id_warehouse' => 2,
            ],
            [
                'name' => 'Fayza Dzakiyyah',
                'email' => 'staff5@stardust.com',
                'role' => User::ROLE_STAFF_GUDANG,
                'id_warehouse' => 2,
            ],
        ];

        foreach ($staffUsers as $s) {
            $user = User::updateOrCreate(
                ['email' => $s['email']],
                [
                    'name' => $s['name'],
                    'password' => Hash::make('password'),
                    'role' => $s['role'] ?? User::ROLE_STAFF_GUDANG,
                    'id_warehouse' => $s['id_warehouse'],
                    'company_id' => (int) config('stardust.tenant_id', 1),
                ]
            );

            if ($s['id_warehouse']) {
                DB::table('warehouse_staff')->updateOrInsert(
                    [
                        'warehouse_id' => $s['id_warehouse'],
                        'user_id' => $user->id,
                    ],
                    [
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
        }
    }
}
