<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Models\Enrollment;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class CertificateController extends Controller
{
    // Admin all certificates dekh sakta hai
    public function index(Request $request)
    {
        $perPage = min(max((int) $request->query('per_page', 50), 1), 100);

        $certificates = Certificate::with([
            'student.user',
            'course',
            'project'
        ])->latest('issue_date')->paginate($perPage);

        return response()->json([
            'certificates' => $certificates->items(),
            'meta' => [
                'current_page' => $certificates->currentPage(),
                'last_page' => $certificates->lastPage(),
                'per_page' => $certificates->perPage(),
                'total' => $certificates->total(),
            ],
        ]);
    }

    // Admin single certificate dekh sakta hai
    public function show($id)
    {
        $certificate = Certificate::with([
            'student.user',
            'course',
            'project'
        ])->findOrFail($id);

        return response()->json([
            'certificate' => $certificate
        ]);
    }

    // Admin certificate generate/create
    public function store(Request $request)
    {
        $validated = $request->validate([
            'student_id' => 'required|exists:students,id',
            'course_id' => 'required|exists:courses,id',
            'project_id' => 'nullable|exists:projects,id',
            'score' => 'nullable|numeric|min:0|max:100',
            'grade' => 'nullable|string|max:20',
            'issue_date' => 'nullable|date',
            'issued_by' => 'nullable|string|max:255',
            'signatures' => 'nullable|array',
            'signatures.*.name' => 'nullable|string|max:255',
            'signatures.*.role' => 'nullable|string|max:255',
            'signatures.*.signature_image' => 'nullable|string|max:255',
        ]);

        $existingCertificate = Certificate::where('student_id', $validated['student_id'])
            ->where('course_id', $validated['course_id'])
            ->first();

        if ($existingCertificate) {
            return response()->json([
                'message' => 'Certificate already exists for this student and course.',
                'certificate' => $existingCertificate->load(['student.user', 'course', 'project'])
            ], 409);
        }

        $certificateCode = 'LS-' . strtoupper(Str::random(8));

        $verificationUrl = URL::to('/certificates/verify/' . $certificateCode);

        // Agar admin ne score/grade nahi diye, to enrollment ka final quiz score use karo
        $score = $validated['score'] ?? null;
        $grade = $validated['grade'] ?? null;

        if ($score === null || $grade === null) {
            $enrollment = Enrollment::where('student_id', $validated['student_id'])
                ->where('course_id', $validated['course_id'])
                ->first();

            if ($enrollment?->final_quiz_score !== null) {
                $score = $score ?? (float) $enrollment->final_quiz_score;
                $grade = $grade ?? $this->computeGrade((float) $enrollment->final_quiz_score);
            }
        }

        $certificate = Certificate::create([
            'student_id' => $validated['student_id'],
            'course_id' => $validated['course_id'],
            'project_id' => $validated['project_id'] ?? null,
            'certificate_number' => 'CERT-' . date('Y') . '-' . strtoupper(Str::random(8)),
            'certificate_code' => $certificateCode,
            'issue_date' => $validated['issue_date'] ?? now()->toDateString(),
            'score' => $score,
            'grade' => $grade,
            'issued_by' => $validated['issued_by'] ?? null,
            'signatures' => $validated['signatures'] ?? [],
            'verification_url' => $verificationUrl,
        ]);

        Notification::create([
            'user_id' => $certificate->student->user_id,
            'title' => 'Certificate Issued',
            'message' => 'Your course completion certificate is now available for the course: ' . ($certificate->course->title ?? ''),
            'type' => 'certificate',
            'related_id' => $certificate->id,
        ]);

        return response()->json([
            'message' => 'Certificate generated successfully',
            'certificate' => $certificate->load(['student.user', 'course', 'project'])
        ], 201);
    }

    // Admin certificate delete (galati se issue kiye par delete kar sakta hai)
    public function destroy($id)
    {
        $certificate = Certificate::findOrFail($id);

        Notification::where('type', 'certificate')
            ->where('related_id', $certificate->id)
            ->delete();

        $certificate->delete();

        return response()->json([
            'message' => 'Certificate deleted successfully.'
        ]);
    }

    // Percentage score -> letter grade
    private function computeGrade(float $score): string
    {
        return match (true) {
            $score >= 90 => 'A+',
            $score >= 80 => 'A',
            $score >= 70 => 'B+',
            $score >= 60 => 'B',
            $score >= 50 => 'C',
            $score >= 40 => 'D',
            default => 'Fail',
        };
    }
}