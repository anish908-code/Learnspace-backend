<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Models\Enrollment;
use App\Models\Notification;
use App\Models\Project;
use App\Models\Submission;
use Illuminate\Http\Request;
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

        // Certificate sirf tabhi issue ho jab student ne course poora kiya ho
        // aur project submission approved ho. warna adhoori certificate ban jayega.
        $eligibility = $this->checkEligibility(
            (int) $validated['student_id'],
            (int) $validated['course_id'],
            $validated['project_id'] ?? null,
        );

        if ($eligibility['error']) {
            return response()->json([
                'message' => $eligibility['error'],
            ], 422);
        }

        $enrollment = $eligibility['enrollment'];
        $approvedSubmission = $eligibility['submission'];
        $projectId = $approvedSubmission?->project_id ?? null;

        // Agar admin ne score/grade nahi diye, to enrollment ka final quiz score use karo
        $score = $validated['score'] ?? null;
        $grade = $validated['grade'] ?? null;

        if ($score === null || $grade === null) {
            if ($enrollment?->final_quiz_score !== null) {
                $score = $score ?? (float) $enrollment->final_quiz_score;
                $grade = $grade ?? $this->computeGrade((float) $enrollment->final_quiz_score);
            }
        }

        $certificate = Certificate::create([
            'student_id' => $validated['student_id'],
            'course_id' => $validated['course_id'],
            'project_id' => $projectId,
            'certificate_number' => 'CERT-' . date('Y') . '-' . strtoupper(Str::random(8)),
            'issue_date' => $validated['issue_date'] ?? now()->toDateString(),
            'score' => $score,
            'grade' => $grade,
            'issued_by' => $validated['issued_by'] ?? null,
            'signatures' => $validated['signatures'] ?? [],
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

    // Certificate eligibility: enrollment complete + project submission approved
    private function checkEligibility(int $studentId, int $courseId, ?int $projectId = null): array
    {
        $enrollment = Enrollment::where('student_id', $studentId)
            ->where('course_id', $courseId)
            ->first();

        if (!$enrollment) {
            return ['error' => 'Student is not enrolled in this course.'];
        }

        if (!$enrollment->completed) {
            return ['error' => 'Course is not completed yet. Student must finish all lessons and pass the final quiz first.'];
        }

        // Course ka project nikalo. Admin ne specific project diya hai to use hi use karo,
        // warna course ka pehla project maano.
        $project = $projectId
            ? Project::where('id', $projectId)->where('course_id', $courseId)->first()
            : Project::where('course_id', $courseId)->orderBy('id')->first();

        // Course me koi project hi nahi hai, to certificate project ke bina issue ho sakta hai
        if (!$project) {
            return [
                'error' => null,
                'enrollment' => $enrollment,
                'submission' => null,
            ];
        }

        $submission = Submission::where('student_id', $studentId)
            ->where('project_id', $project->id)
            ->where('status', 'approved')
            ->latest('reviewed_at')
            ->first();

        if (!$submission) {
            $pending = Submission::where('student_id', $studentId)
                ->where('project_id', $project->id)
                ->latest('submitted_at')
                ->first();

            if (!$pending) {
                return ['error' => 'Student has not submitted the course project yet.'];
            }

            return [
                'error' => 'Project submission is not approved yet. Current status: '
                    . str_replace('_', ' ', $pending->status) . '.',
            ];
        }

        return [
            'error' => null,
            'enrollment' => $enrollment,
            'submission' => $submission,
        ];
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