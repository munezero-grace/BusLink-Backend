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
        // Create Admin User
        User::updateOrCreate([
            'email' => 'admin@buslink.com',
        ], [
            'name' => 'Admin User',
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'card_number' => 'BL-ADMIN001',
            'phone_number' => '1234567890',
            'status' => 'active'
        ]);

        // Create Driver User
        User::updateOrCreate([
            'email' => 'driver@buslink.com',
        ], [
            'name' => 'Test Driver',
            'password' => Hash::make('password123'),
            'role' => 'driver',
            'card_number' => 'BL-DRIVER001',
            'phone_number' => '1234567891',
            'status' => 'active'
        ]);

        // Create Passenger User
        User::updateOrCreate([
            'email' => 'passenger@buslink.com',
        ], [
            'name' => 'Test Passenger',
            'password' => Hash::make('password123'),
            'role' => 'passenger',
            'card_number' => 'BL-PASS001',
            'phone_number' => '1234567892',
            'status' => 'active'
        ]);
    }
}
