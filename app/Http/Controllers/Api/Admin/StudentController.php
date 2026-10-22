<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\Project;
use App\Models\Student;
use App\Models\Submission;
use Illuminate\Http\Request;

class StudentController extends Controller
{
    // View all students
    public function index(Request $request)
    {
        $perPage = min(max((int) $request->query('per_page', 50), 1), 100);

        $students = Student::with('user')->paginate($perPage);

        $studentIds = $students->getCollection()->pluck('id')->all();

        $progressMap = $this->progressForStudents($studentIds);

        // Har student ke saath uska progress summary chipka do
        $students->getCollection()->each(function (Student $student) use ($progressMap) {
            $student->setAttribute('progress', $progressMap[$student->id] ?? $this->emptyProgress());
        });

        return response()->json([
            'students' => $students->items(),
            'summary' => $this->buildSummary($progressMap),
            'meta' => [
                'current_page' => $students->currentPage(),
                'last_page' => $students->lastPage(),
                'per_page' => $students->perPage(),
                'total' => $students->total(),
            ],
        ]);
    }

    // View single student details
    public function show($id)
    {
        $student = Student::with('user')->findOrFail($id);

        $student->setAttribute('progress', $this->progressForStudents([$student->id])[$student->id] ?? $this->emptyProgress());

        return response()->json([
            'student' => $student
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Progress aggregation
    |--------------------------------------------------------------------------
    | Har student ka learning stage nikalne ke liye 3 aggregate queries.
    | N+1 loop nahi banaya — saare students ke liye ek hi pass me counts aate hain.
    |
    | Pipeline: enrolled -> lessons done (0-80%) -> quiz passed (100%)
    |           -> project submitted -> certificate issued
    */

    private function progressForStudents(array $studentIds): array
    {
        if (empty($studentIds)) {
            return [];
        }

        $enrollments = Enrollment::whereIn('student_id', $studentIds)
            ->selectRaw('student_id,
                COUNT(*) as enrolled,
                SUM(CASE WHEN completed = 1 THEN 1 ELSE 0 END) as completed_courses,
                SUM(CASE WHEN final_quiz_passed = 1 THEN 1 ELSE 0 END) as quizzes_passed,
                SUM(CASE WHEN lessons_completed > 0 AND completed = 0 THEN 1 ELSE 0 END) as learning_courses,
                SUM(CASE WHEN lessons_completed = 0 AND completed = 0 THEN 1 ELSE 0 END) as just_enrolled,
                SUM(lessons_completed) as lessons_completed,
                AVG(progress) as avg_progress')
            ->groupBy('student_id')
            ->get()
            ->keyBy('student_id');

        $submissions = Submission::whereIn('student_id', $studentIds)
            ->selectRaw("student_id,
                COUNT(*) as submissions,
                SUM(CASE WHEN status IN ('pending', 'under_review') THEN 1 ELSE 0 END) as pending_submissions,
                SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_submissions,
                SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_submissions")
            ->groupBy('student_id')
            ->get()
            ->keyBy('student_id');

        $certificates = Certificate::whereIn('student_id', $studentIds)
            ->selectRaw('student_id, COUNT(*) as certificates')
            ->groupBy('student_id')
            ->get()
            ->keyBy('student_id');

        /*
         * courseBreakdown ke liye batched data — har course/student ke liye
         * alag query nahi, sab ek hi pass me.
         */
        $enrollmentRows = Enrollment::whereIn('student_id', $studentIds)
            ->with('course:id,title')
            ->get();

        $courseIds = $enrollmentRows->pluck('course_id')->unique()->values()->all();

        $lessonCounts = $courseIds
            ? Lesson::whereIn('course_id', $courseIds)
                ->where('status', 'published')
                ->selectRaw('course_id, COUNT(*) as total')
                ->groupBy('course_id')
                ->pluck('total', 'course_id')
                ->all()
            : [];

        $projectsByCourse = $courseIds
            ? Project::whereIn('course_id', $courseIds)
                ->orderBy('id')
                ->get(['id', 'course_id'])
                ->groupBy('course_id')
                ->map(fn ($group) => $group->first())
                ->all()
            : [];

        $submissionRows = Submission::whereIn('student_id', $studentIds)->get();

        $submissionByProject = $submissionRows
            ->groupBy('student_id')
            ->map(fn ($group) => $group->keyBy('project_id'))
            ->all();

        $certificateCourseIds = Certificate::whereIn('student_id', $studentIds)
            ->get(['student_id', 'course_id'])
            ->groupBy('student_id')
            ->map(fn ($group) => $group->pluck('course_id', 'course_id')->all())
            ->all();

        $context = [
            'enrollments' => $enrollmentRows->groupBy('student_id')->all(),
            'lessonCounts' => $lessonCounts,
            'projectsByCourse' => $projectsByCourse,
            'submissionByProject' => $submissionByProject,
            'certificateCourseIds' => $certificateCourseIds,
        ];

        $result = [];

        foreach ($studentIds as $studentId) {
            $enrollment = $enrollments->get($studentId);
            $submission = $submissions->get($studentId);
            $certificate = $certificates->get($studentId);

            $enrolled = (int) ($enrollment->enrolled ?? 0);
            $completedCourses = (int) ($enrollment->completed_courses ?? 0);
            $quizzesPassed = (int) ($enrollment->quizzes_passed ?? 0);
            $learningCourses = (int) ($enrollment->learning_courses ?? 0);
            $justEnrolled = (int) ($enrollment->just_enrolled ?? 0);
            $lessonsCompleted = (int) ($enrollment->lessons_completed ?? 0);
            $avgProgress = round((float) ($enrollment->avg_progress ?? 0), 2);

            $totalSubmissions = (int) ($submission->submissions ?? 0);
            $pendingSubmissions = (int) ($submission->pending_submissions ?? 0);
            $approvedSubmissions = (int) ($submission->approved_submissions ?? 0);
            $rejectedSubmissions = (int) ($submission->rejected_submissions ?? 0);
            $certificateCount = (int) ($certificate->certificates ?? 0);

            $result[$studentId] = [
                'enrolled' => $enrolled,
                'completed_courses' => $completedCourses,
                'quizzes_passed' => $quizzesPassed,
                'learning_courses' => $learningCourses,
                'just_enrolled' => $justEnrolled,
                'lessons_completed' => $lessonsCompleted,
                'avg_progress' => $avgProgress,
                'submissions' => $totalSubmissions,
                'pending_submissions' => $pendingSubmissions,
                'approved_submissions' => $approvedSubmissions,
                'rejected_submissions' => $rejectedSubmissions,
                'certificates' => $certificateCount,
                'stage' => $this->resolveStage(
                    $enrolled,
                    $completedCourses,
                    $learningCourses,
                    $pendingSubmissions,
                    $approvedSubmissions,
                    $certificateCount,
                ),
                'courses' => $this->courseBreakdown($studentId, $context),
            ];
        }

        return $result;
    }

    /*
    |--------------------------------------------------------------------------
    | Per-course breakdown
    |--------------------------------------------------------------------------
    | Har enrolled course ka current stage + "aage kya baki hai" nikalta hai,
    | taaki admin ko progress percent se zyada actionable cheez dikhe.
    */

    private function courseBreakdown(int $studentId, array $context): array
    {
        $courses = [];

        $studentEnrollments = $context['enrollments'][$studentId] ?? [];

        foreach ($studentEnrollments as $enrollment) {
            $courseId = (int) $enrollment->course_id;

            $totalLessons = (int) ($context['lessonCounts'][$courseId] ?? 0);
            $doneLessons = min((int) $enrollment->lessons_completed, $totalLessons);
            $lessonsLeft = max($totalLessons - $doneLessons, 0);

            $project = $context['projectsByCourse'][$courseId] ?? null;
            $submission = $project
                ? ($context['submissionByProject'][$studentId][$project['id']] ?? null)
                : null;

            $quizPassed = (bool) $enrollment->final_quiz_passed;
            $courseCompleted = (bool) $enrollment->completed;

            $quizStatus = $this->resolveQuizStatus(
                $lessonsLeft,
                $totalLessons,
                $quizPassed,
                $enrollment->final_quiz_score,
            );

            $projectStatus = $project
                ? $this->resolveProjectStatus($submission)
                : 'none';

            $hasCertificate = isset($context['certificateCourseIds'][$studentId][$courseId]);

            $remaining = [];

            if ($lessonsLeft > 0) {
                $remaining[] = $lessonsLeft === 1
                    ? '1 lesson left'
                    : "{$lessonsLeft} lessons left";
            }

            if ($quizStatus === 'not_attempted') {
                $remaining[] = 'Final quiz not attempted';
            } elseif ($quizStatus === 'failed') {
                $remaining[] = 'Final quiz failed — retake needed';
            } elseif ($quizStatus === 'locked') {
                $remaining[] = 'Final quiz locked';
            }

            if ($projectStatus === 'not_submitted') {
                $remaining[] = 'Project not submitted';
            } elseif ($projectStatus === 'in_review') {
                $remaining[] = 'Project awaiting review';
            } elseif ($projectStatus === 'rejected') {
                $remaining[] = 'Project rejected — resubmit';
            } elseif ($projectStatus === 'approved' && ! $hasCertificate) {
                $remaining[] = 'Certificate not issued yet';
            }

            $courses[] = [
                'course_id' => $courseId,
                'course_title' => $enrollment->course?->title
                    ?? "Course #{$courseId}",
                'progress' => round((float) $enrollment->progress, 2),
                'lessons_completed' => $doneLessons,
                'total_lessons' => $totalLessons,
                'lessons_left' => $lessonsLeft,
                'quiz' => $quizStatus,
                'quiz_score' => $enrollment->final_quiz_score !== null
                    ? round((float) $enrollment->final_quiz_score, 2)
                    : null,
                'project' => $projectStatus,
                'certified' => $hasCertificate,
                'stage' => $this->resolveCourseStage(
                    $hasCertificate,
                    $projectStatus,
                    $quizStatus,
                    $doneLessons,
                    $courseCompleted,
                ),
                'remaining' => $remaining,
            ];
        }

        return $courses;
    }

    private function resolveQuizStatus(
        int $lessonsLeft,
        int $totalLessons,
        bool $passed,
        $score,
    ): string {
        if ($passed) {
            return 'passed';
        }

        if ($lessonsLeft > 0) {
            return 'locked';
        }

        if ($totalLessons === 0) {
            return $score === null ? 'not_attempted' : 'failed';
        }

        return $score === null ? 'not_attempted' : 'failed';
    }

    private function resolveProjectStatus(?Submission $submission): string
    {
        if (! $submission) {
            return 'not_submitted';
        }

        return match ($submission->status) {
            'approved' => 'approved',
            'pending', 'under_review' => 'in_review',
            'rejected', 'changes_required' => 'rejected',
            default => 'not_submitted',
        };
    }

    private function resolveCourseStage(
        bool $hasCertificate,
        string $projectStatus,
        string $quizStatus,
        int $doneLessons,
        bool $courseCompleted,
    ): string {
        if ($hasCertificate) {
            return 'done';
        }

        if ($projectStatus === 'approved') {
            return 'awaiting_certificate';
        }

        if ($projectStatus === 'in_review') {
            return 'project_review';
        }

        if ($courseCompleted || $quizStatus === 'passed') {
            return 'project_pending';
        }

        if ($quizStatus === 'failed' || $quizStatus === 'not_attempted') {
            return 'quiz_pending';
        }

        if ($doneLessons > 0) {
            return 'learning';
        }

        return 'not_started';
    }

    // Student abhi kahan tak pahuncha hai — sabse advanced stage se decide karo
    private function resolveStage(
        int $enrolled,
        int $completedCourses,
        int $learningCourses,
        int $pendingSubmissions,
        int $approvedSubmissions,
        int $certificateCount,
    ): array {
        if ($enrolled === 0) {
            return ['key' => 'not_enrolled', 'label' => 'Not Enrolled'];
        }

        if ($certificateCount > 0) {
            return ['key' => 'certified', 'label' => 'Certified'];
        }

        if ($approvedSubmissions > 0) {
            return ['key' => 'project_approved', 'label' => 'Project Approved'];
        }

        if ($pendingSubmissions > 0) {
            return ['key' => 'project_review', 'label' => 'Project In Review'];
        }

        if ($completedCourses > 0) {
            return ['key' => 'quiz_passed', 'label' => 'Quiz Passed'];
        }

        if ($learningCourses > 0) {
            return ['key' => 'learning', 'label' => 'Learning'];
        }

        return ['key' => 'enrolled', 'label' => 'Enrolled'];
    }

    // Table ke upar summary strip ke liye
    private function buildSummary(array $progressMap): array
    {
        $summary = [
            'total_students' => count($progressMap),
            'enrolled' => 0,
            'learning' => 0,
            'quiz_passed' => 0,
            'project_review' => 0,
            'certified' => 0,
            'not_enrolled' => 0,
            'pending_submissions' => 0,
        ];

        foreach ($progressMap as $progress) {
            $summary['enrolled'] += $progress['enrolled'] > 0 ? 1 : 0;
            $summary['pending_submissions'] += $progress['pending_submissions'];
            $summary[$progress['stage']['key']] = ($summary[$progress['stage']['key']] ?? 0) + 1;
        }

        return $summary;
    }

    private function emptyProgress(): array
    {
        return [
            'enrolled' => 0,
            'completed_courses' => 0,
            'quizzes_passed' => 0,
            'learning_courses' => 0,
            'just_enrolled' => 0,
            'lessons_completed' => 0,
            'avg_progress' => 0.0,
            'submissions' => 0,
            'pending_submissions' => 0,
            'approved_submissions' => 0,
            'rejected_submissions' => 0,
                'certificates' => 0,
                'stage' => ['key' => 'not_enrolled', 'label' => 'Not Enrolled'],
                'courses' => [],
            ];
    }
}
