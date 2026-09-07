<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonCompletion;
use App\Models\Notification;
use App\Models\Quiz;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class LessonController extends Controller
{
    // View all lessons of a course
    public function index(Request $request, $courseId)
    {
        $student = $request->user()->student;
        $perPage = min(max((int) $request->query('per_page', 50), 1), 100);

        $enrollment = Enrollment::where('student_id', $student->id)
            ->where('course_id', $courseId)
            ->first();

        if (!$enrollment) {
            return response()->json([
                'message' => 'You are not enrolled in this course'
            ], 403);
        }

        $lessons = Lesson::where('course_id', $courseId)
            ->where('status', 'published')
            ->orderBy('lesson_order')
            ->paginate($perPage);

        return response()->json([
            'lessons' => $lessons->items(),
            'meta' => [
                'current_page' => $lessons->currentPage(),
                'last_page' => $lessons->lastPage(),
                'per_page' => $lessons->perPage(),
                'total' => $lessons->total(),
            ],
        ]);
    }

    // View single lesson
    public function show(Request $request, $id)
    {
        $student = $request->user()->student;
        $lesson = Lesson::where('status', 'published')->findOrFail($id);
        $enrollment = Enrollment::where('student_id', $student->id)
            ->where('course_id', $lesson->course_id)
            ->first();

        if (!$enrollment) {
            return response()->json([
                'message' => 'You are not enrolled in this course'
            ], 403);
        }

        $lessonData = $lesson->toArray();
        $lessonData['notes_content'] = null;
        if ($lesson->notes_file && Storage::disk('local')->exists($lesson->notes_file)) {
            $lessonData['notes_content'] = Storage::disk('local')->get($lesson->notes_file);
        }

        return response()->json([
            'lesson' => $lessonData
        ]);
    }

    public function markComplete(Request $request, $id)
    {
        $student = $request->user()->student;
        $lesson = Lesson::where('status', 'published')->findOrFail($id);

        $enrollment = Enrollment::where('student_id', $student->id)
            ->where('course_id', $lesson->course_id)
            ->first();

        if (!$enrollment) {
            return response()->json([
                'message' => 'You are not enrolled in this course'
            ], 403);
        }

        $completion = LessonCompletion::firstOrCreate([
            'enrollment_id' => $enrollment->id,
            'lesson_id' => $lesson->id,
        ], [
            'completed_at' => now(),
        ]);

        $totalLessons = Lesson::where('course_id', $lesson->course_id)
            ->where('status', 'published')
            ->count();
        $completedLessons = LessonCompletion::where('enrollment_id', $enrollment->id)->count();
        $lessonProgress = $totalLessons > 0
            ? round(($completedLessons / $totalLessons) * 80, 2)
            : 0;

        $enrollment->update([
            'lessons_completed' => $completedLessons,
            'progress' => max($enrollment->progress, $lessonProgress),
        ]);

        if ($totalLessons > 0 && $completedLessons >= $totalLessons) {
            $quiz = Quiz::where('course_id', $lesson->course_id)
                ->where('status', 'published')
                ->first();

            if ($quiz) {
                $notificationExists = Notification::where('user_id', $request->user()->id)
                    ->where('type', 'final_quiz')
                    ->where('related_id', $quiz->id)
                    ->exists();
                if (!$notificationExists) {
                    Notification::create([
                        'user_id' => $request->user()->id,
                        'title' => 'Final Quiz Available',
                        'message' => 'All lessons completed. Your final quiz is now unlocked.',
                        'type' => 'final_quiz',
                        'related_id' => $quiz->id,
                    ]);
                }
            }
        }

        return response()->json([
            'message' => $completion->wasRecentlyCreated
                ? 'Lesson marked as complete'
                : 'Lesson was already completed',
            'enrollment' => $enrollment->fresh(),
        ]);
    }
}
