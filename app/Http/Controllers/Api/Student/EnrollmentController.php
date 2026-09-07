<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Models\Lesson;
use Illuminate\Http\Request;

class EnrollmentController extends Controller
{
    // Student ke enrolled courses dekhna
    public function index(Request $request)
    {
        $student = $request->user()->student;
        $perPage = min(max((int) $request->query('per_page', 50), 1), 100);

        $enrollments = Enrollment::with('course')
            ->where('student_id', $student->id)
            ->paginate($perPage);

        return response()->json([
            'enrollments' => $enrollments->items(),
            'meta' => [
                'current_page' => $enrollments->currentPage(),
                'last_page' => $enrollments->lastPage(),
                'per_page' => $enrollments->perPage(),
                'total' => $enrollments->total(),
            ],
        ]);
    }

    // Course me enroll hona
    public function store(Request $request)
    {
        $request->validate([
            'course_id' => 'required|exists:courses,id',
        ]);

        $student = $request->user()->student;

        $existingEnrollment = Enrollment::where('student_id', $student->id)
            ->where('course_id', $request->course_id)
            ->first();

        if ($existingEnrollment) {
            return response()->json([
                'message' => 'Already enrolled in this course'
            ], 409);
        }

        $enrollment = Enrollment::create([
            'student_id' => $student->id,
            'course_id' => $request->course_id,
            'progress' => 0,
            'lessons_completed' => 0,
            'final_quiz_score' => null,
            'final_quiz_passed' => false,
            'completed' => false,
            'started_at' => now(),
            'completed_at' => null,
        ]);

        return response()->json([
            'message' => 'Course enrolled successfully',
            'enrollment' => $enrollment->load('course')
        ], 201);
    }

    // Ek enrollment ki details dekhna
    public function show(Request $request, $id)
    {
        $student = $request->user()->student;

        $enrollment = Enrollment::with('course')
            ->where('student_id', $student->id)
            ->findOrFail($id);

        return response()->json([
            'enrollment' => $enrollment
        ]);
    }

    // Course progress sync karna (server-side calculated)
    public function update(Request $request, $id)
    {
        $student = $request->user()->student;

        $enrollment = Enrollment::where('student_id', $student->id)
            ->findOrFail($id);

        $totalLessons = Lesson::where('course_id', $enrollment->course_id)
            ->where('status', 'published')
            ->count();
        $completedLessons = $enrollment->lessonCompletions()->count();

        $lessonProgress = $totalLessons > 0
            ? round(($completedLessons / $totalLessons) * 80, 2)
            : 0;

        $enrollment->update([
            'lessons_completed' => min($completedLessons, $totalLessons),
            'progress' => $enrollment->completed
                ? 100
                : max($enrollment->progress, $lessonProgress),
        ]);

        return response()->json([
            'message' => 'Enrollment progress updated successfully',
            'enrollment' => $enrollment->load('course')
        ]);
    }
}
