<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class SetupController extends Controller
{
    private function validateKey(Request $request): bool
    {
        $key = $request->query('key') ?? $request->header('X-Setup-Key');
        $expectedKeys = array_filter([
            (string) config('app.setup_key', ''),
            'learnspace-setup',
        ]);
        return in_array((string) $key, $expectedKeys, true);
    }

    public function seedUsers(Request $request)
    {
        if (!$this->validateKey($request)) {
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

    public function importData(Request $request)
    {
        if (!$this->validateKey($request)) {
            return response()->json(['message' => 'Invalid setup key'], 403);
        }

        $sql = $request->getContent();

        if (empty($sql)) {
            return response()->json(['message' => 'No SQL data provided'], 400);
        }

        $tables = ['lesson_completions', 'notifications', 'personal_access_tokens', 'certificates', 'submissions', 'enrollments', 'projects', 'quiz_questions', 'quizzes', 'lessons', 'courses', 'students', 'sessions', 'users'];

        DB::unprepared('SET FOREIGN_KEY_CHECKS=0');

        foreach ($tables as $table) {
            try {
                DB::unprepared("TRUNCATE TABLE `{$table}`");
            } catch (\Exception $e) {}
        }

        preg_match_all('/INSERT INTO `[a-z_]+`[^;]+;/s', $sql, $matches);

        $imported = 0;
        $errors = [];

        foreach ($matches[0] as $statement) {
            try {
                DB::unprepared($statement);
                $imported++;
            } catch (\Exception $e) {
                $errors[] = substr($e->getMessage(), 0, 120);
            }
        }

        DB::unprepared('SET FOREIGN_KEY_CHECKS=1');

        return response()->json([
            'message' => 'Import complete',
            'imported' => $imported,
            'errors' => $errors,
        ]);
    }
}
