<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Admin Users - Add as many as you want
        $admins = [
            ['name' => 'Anish', 'email' => 'anish@gmail.com', 'password' => 'anish123456'],
            // Add more admins below with different passwords
            ['name' => 'Raj', 'email' => 'raj@gmail.com', 'password' => 'raj123456'],
            ['name' => 'Rishabh', 'email' => 'rishabh@gmail.com', 'password' => 'rishabh123456'],
        ];

        foreach ($admins as $admin) {
            User::create([
                'name' => $admin['name'],
                'email' => $admin['email'],
                'password' => Hash::make($admin['password']),
                'role' => 'admin',
            ]);
        }
    }
}
