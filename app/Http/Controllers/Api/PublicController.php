<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;

class PublicController extends Controller
{
    public function index()
    {
        $stats = [
            'learners' => User::where('role', 'student')->count(),
            'courses' => Course::count(),
            'enrollments' => Enrollment::count(),
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

    // Public certificate verification - no authentication required
    public function verifyCertificate($certificateCode)
    {
        $certificate = Certificate::with([
            'student.user',
            'course'
        ])
            ->where('certificate_code', $certificateCode)
            ->orWhere('certificate_number', $certificateCode)
            ->first();

        if (!$certificate) {
            return response()->json([
                'valid' => false,
                'message' => 'Certificate not found',
            ], 404);
        }

        return response()->json([
            'valid' => true,
            'certificate' => [
                'id' => $certificate->id,
                'certificate_code' => $certificate->certificate_code,
                'student' => [
                    'name' => $certificate->student?->user?->name ?? 'Student',
                ],
                'course' => [
                    'title' => $certificate->course?->title ?? 'Course',
                ],
                'issue_date' => $certificate->issue_date?->toDateString(),
                'score' => $certificate->score !== null
                    ? round((float) $certificate->score, 2)
                    : null,
                'grade' => $certificate->grade,
                'issued_by' => $certificate->issued_by,
            ],
        ]);
    }
}
