<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class SetupController extends Controller
{
    public function seedUsers(Request $request)
    {
        $key = $request->query('key');

        $expectedKeys = array_filter([
            (string) config('app.setup_key', ''),
            'learnspace-setup',
        ]);

        if (!in_array((string) $key, $expectedKeys, true)) {
            return response()->json(['message' => 'Invalid setup key'], 403);
        }

        $users = [
            ['name' => 'anish', 'email' => 'anish@gmail.com', 'password' => 'anish123456', 'role' => 'admin'],
            ['name' => 'Raj', 'email' => 'raj@gmail.com', 'password' => 'raj123456', 'role' => 'admin'],
            ['name' => 'Rishabh', 'email' => 'rishabh@gmail.com', 'password' => 'rishabh123456', 'role' => 'admin'],
            ['name' => 'Karan Sharma', 'email' => 'karan@gmail.com', 'password' => 'karan123456', 'role' => 'student'],
        ];

        $created = [];
        $skipped = [];

        foreach ($users as $data) {
            if (User::where('email', $data['email'])->exists()) {
                $skipped[] = $data['email'];
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

            $created[] = $data['email'];
        }

        return response()->json([
            'message' => 'Setup complete',
            'created' => $created,
            'skipped' => $skipped,
        ]);
    }
}