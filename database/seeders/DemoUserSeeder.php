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
                'name' => 'Demo User',
                'email' => 'demo@example.com',
                'password' => Hash::make('password'),
            ],
            [
                'name' => 'Beach Explorer',
                'email' => 'explorer@beachrex.com',
                'password' => Hash::make('password'),
            ],
            [
                'name' => 'Travel Guide',
                'email' => 'guide@beachrex.com',
                'password' => Hash::make('password'),
            ],
            [
                'name' => 'Admin User',
                'email' => 'admin@beachrex.com',
                'password' => Hash::make('admin123'),
            ]
        ];

        foreach ($users as $userData) {
            User::updateOrCreate(
                ['email' => $userData['email']],
                [
                    'name' => $userData['name'],
                    'password' => $userData['password'],
                ]
            );
        }

        $this->command->info('Demo users created successfully.');
    }
}
