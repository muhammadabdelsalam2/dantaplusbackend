<?php

namespace Database\Seeders\Users;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Seeder;

class SuperAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
              $superAdmin = User::firstOrCreate(
            ['email' => 'admin@system.com'],
            [
                'name' => 'System Super Admin',
                'password' => Hash::make('Admin@123'),
                'is_active' => true,
            ]
        );

        if (!$superAdmin->hasRole('super-admin')) {
            $superAdmin->assignRole('super-admin');
        }
        //
    }
}
