<?php

namespace Database\Seeders;

use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class ProductionUsersSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            ['name' => 'anish', 'email' => 'anish@gmail.com', 'password' => 'anish123456', 'role' => 'admin'],
            ['name' => 'Raj', 'email' => 'raj@gmail.com', 'password' => 'raj123456', 'role' => 'admin'],
            ['name' => 'Rishabh', 'email' => 'rishabh@gmail.com', 'password' => 'rishabh123456', 'role' => 'admin'],
            ['name' => 'Karan Sharma', 'email' => 'karan@gmail.com', 'password' => 'karan123456', 'role' => 'student'],
        ];

        foreach ($users as $data) {
            $existing = User::where('email', $data['email'])->first();

            if ($existing) {
                $this->command->warn("Skipping {$data['email']} (already exists)");
                continue;
            }

            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'role' => $data['role'],
            ]);

            if ($data['role'] === 'student') {
                Student::create(['user_id' => $user->id]);
            }

            $this->command->info("Created {$data['role']}: {$data['email']}");
        }
    }
}
