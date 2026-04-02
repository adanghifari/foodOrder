<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Create or update Admin User
        $admin = User::updateOrCreate(
            ['username' => 'admin'],
            [
                'name' => 'Administrator',
                'password' => Hash::make('Administrator123!'),
                'role' => 'ADMIN',
            ]
        );

        // 2. Create or update Customer
        $customer = User::updateOrCreate(
            ['username' => 'customer'],
            [
                'name' => 'Customer Demo',
                'password' => Hash::make('customer123'),
                'role' => 'CUSTOMER',
            ]
        );
        
        $this->command->info('Database seeded successfully with admin and customer users only.');
    }
}
