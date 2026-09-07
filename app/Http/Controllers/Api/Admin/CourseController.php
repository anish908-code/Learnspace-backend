<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use Illuminate\Http\Request;

class CourseController extends Controller
{
    // View all courses
    public function index(Request $request)
    {
        $perPage = min(max((int) $request->query('per_page', 50), 1), 100);

        $courses = Course::paginate($perPage);

        return response()->json([
            'courses' => $courses->items(),
            'meta' => [
                'current_page' => $courses->currentPage(),
                'last_page' => $courses->lastPage(),
                'per_page' => $courses->perPage(),
                'total' => $courses->total(),
            ],
        ]);
    }

    // View single course
    public function show($id)
    {
        $course = Course::with([
            'lessons',
            'quizzes',
            'projects'
        ])->findOrFail($id);

        return response()->json([
            'course' => $course
        ]);
    }

    // Create course
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'category' => 'required|string|max:100',
            'difficulty' => 'required|string|max:50',
            'duration' => 'nullable|string|max:100',
            'status' => 'required|in:draft,published',
        ]);

        $course = Course::create($validated);

        return response()->json([
            'message' => 'Course created successfully',
            'course' => $course
        ], 201);
    }

    // Update course
    public function update(Request $request, $id)
    {
        $course = Course::findOrFail($id);

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'category' => 'sometimes|string|max:100',
            'difficulty' => 'sometimes|string|max:50',
            'thumbnail' => 'nullable|string|max:255',
            'duration' => 'nullable|string|max:100',
            'status' => 'sometimes|in:draft,published',
        ]);

        $course->update($validated);

        return response()->json([
            'message' => 'Course updated successfully',
            'course' => $course
        ]);
    }

    // Delete course
    public function destroy($id)
    {
        $course = Course::findOrFail($id);

        $hasEnrollments = $course->enrollments()->exists();
        if ($hasEnrollments) {
            return response()->json([
                'message' => 'Cannot delete this course because students are enrolled in it. Remove or archive it instead.'
            ], 409);
        }

        $course->delete();

        return response()->json([
            'message' => 'Course deleted successfully'
        ]);
    }
}
