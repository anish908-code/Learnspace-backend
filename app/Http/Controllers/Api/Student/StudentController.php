<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use App\Models\Student;
use Illuminate\Http\Request;

class StudentController extends Controller
{
    // Student apni profile dekh sakta hai
    public function show(Request $request)
    {
        $student = Student::with('user')
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        return response()->json([
            'student' => $student
        ]);
    }

    // Student apni profile update kar sakta hai
    public function update(Request $request)
    {
        $student = Student::where(
            'user_id',
            $request->user()->id
        )->firstOrFail();

        $validated = $request->validate([
            'college' => 'nullable|string|max:255',
            'course' => 'nullable|string|max:255',
            'semester' => 'nullable|string|max:50',
            'bio' => 'nullable|string',
            'profile_image' => 'nullable|string|max:2048',
            'skills' => 'nullable|string',
        ]);

        $student->update($validated);

        return response()->json([
            'message' => 'Profile updated successfully',
            'student' => $student->load('user'),
        ]);
    }
}
