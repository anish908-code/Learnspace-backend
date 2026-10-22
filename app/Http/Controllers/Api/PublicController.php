<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\User;

class PublicController extends Controller
{
    public function index()
    {
        $stats = [
            'learners' => User::where('role', 'student')->count(),
            'courses' => Course::count(),
            'enrollments' => Enrollment::count(),
            'lessons' => Lesson::count(),
            'certificates' => Certificate::count(),
        ];

        $courses = Course::orderBy('title')
            ->get(['id', 'title', 'description', 'difficulty', 'category', 'thumbnail'])
            ->map(function ($course) {
                $course->lesson_count = $course->lessons()->count();
                $course->enrollment_count = $course->enrollments()->count();
                return $course;
            });

        return response()->json([
            'stats' => $stats,
            'courses' => $courses,
        ]);
    }

}
