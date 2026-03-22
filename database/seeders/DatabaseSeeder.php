<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\MenuItem;
use App\Models\Order;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Create Admin User
        $admin = User::create([
            'username' => 'admin',
            'name' => 'Administrator',
            'password' => Hash::make('admin123'),
            'role' => 'ADMIN',
        ]);

        // 2. Create Customer
        $customer = User::create([
            'username' => 'adan',
            'name' => 'Adan',
            'password' => Hash::make('adan123'),
            'role' => 'CUSTOMER',
        ]);

        // 3. Create Menu Items
        $menu1 = MenuItem::create([
            'menuId' => 1,
            'name' => 'Nasi Goreng Spesial',
            'price' => 25000,
            'category' => 'Makanan',
            'description' => 'Nasi goreng dengan telur, sosis, dan ayam suwir.',
            'status' => 'Available'
        ]);

        $menu2 = MenuItem::create([
            'menuId' => 2,
            'name' => 'Es Teh Manis',
            'price' => 5000,
            'category' => 'Minuman',
            'description' => 'Es teh manis segar pelepas dahaga.',
            'status' => 'Available'
        ]);

        // 4. Create an Order
        Order::create([
            'orderId' => 101,
            'userId' => $customer->_id,
            'customerName' => $customer->name,
            'tableNumber' => 5,
            'status' => 'CONFIRMED',
            'paymentStatus' => 'UNPAID',
            'totalPrice' => 30000,
            'items' => [
                [
                    'menuId' => 1,
                    'name' => 'Nasi Goreng Spesial',
                    'quantity' => 1,
                    'price' => 25000, // Subtotal
                    'unitPrice' => 25000
                ],
                [
                    'menuId' => 2,
                    'name' => 'Es Teh Manis',
                    'quantity' => 1,
                    'price' => 5000, // Subtotal
                    'unitPrice' => 5000
                ]
            ]
        ]);
        
        $this->command->info('Database seeded successfully with users, menus, and an order!');
    }
}
