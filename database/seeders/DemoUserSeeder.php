<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class DemoUserSeeder extends Seeder
{

    public function run(): void
    {
        // Create demo users
        $users = [
            [
                'name' => 'Beach Explorer',
                'email' => 'explorer@beachrex.com',
                'password' => Hash::make('password'),
                'is_admin' => false,
            ],
            [
                'name' => 'Admin User',
                'email' => 'admin@beachrex.com',
                'password' => Hash::make('admin123'),
                'is_admin' => true,
            ]
        ];

        foreach ($users as $userData) {
            User::updateOrCreate(
                ['email' => $userData['email']],
                [
                    'name' => $userData['name'],
                    'password' => $userData['password'],
                    'is_admin' => $userData['is_admin'],
                ]
            );
        }

        $this->command->info('Demo users created successfully.');
    }
}
