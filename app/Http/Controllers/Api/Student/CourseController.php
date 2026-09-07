<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use App\Models\Course;
use Illuminate\Http\Request;

class CourseController extends Controller
{
    // View all published courses
    public function index(Request $request)
    {
        $perPage = min(max((int) $request->query('per_page', 50), 1), 100);

        $courses = Course::where('status', 'published')
            ->paginate($perPage);

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

    // View course details
    public function show($id)
    {
        $course = Course::with([
            'lessons' => function ($query) {
                $query->where('status', 'published')
                    ->orderBy('lesson_order');
            },
            'quizzes' => function ($query) {
                $query->where('status', 'published');
            },
            'projects' => function ($query) {
                $query->where('status', 'published');
            }
        ])
            ->where('status', 'published')
            ->findOrFail($id);

        return response()->json([
            'course' => $course
        ]);
    }
}
