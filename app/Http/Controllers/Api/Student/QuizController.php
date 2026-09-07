<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use App\Models\Quiz;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\Notification;
use Illuminate\Http\Request;

class QuizController extends Controller
{
    // View quiz details with questions
    public function show(Request $request, $id)
    {
        $student = $request->user()->student;

        $quiz = Quiz::with('questions')
            ->where('status', 'published')
            ->findOrFail($id);

        // Check student is enrolled in this quiz's course
        $enrollment = Enrollment::where('student_id', $student->id)
            ->where('course_id', $quiz->course_id)
            ->first();

        if (!$enrollment) {
            return response()->json([
                'message' => 'You are not enrolled in this course'
            ], 403);
        }

        $publishedLessonCount = Lesson::where('course_id', $quiz->course_id)
            ->where('status', 'published')
            ->count();

        if ($publishedLessonCount > 0 && $enrollment->lessons_completed < $publishedLessonCount) {
            return response()->json([
                'message' => 'Final quiz unlocks after all lessons are completed'
            ], 403);
        }

        $quiz->questions->makeHidden('correct_answer');

        return response()->json([
            'quiz' => $quiz
        ]);
    }

    // Submit quiz answers
    public function submit(Request $request, $id)
    {
        $student = $request->user()->student;

        $quiz = Quiz::with('questions')
            ->where('status', 'published')
            ->findOrFail($id);

        // Check enrollment
        $enrollment = Enrollment::where('student_id', $student->id)
            ->where('course_id', $quiz->course_id)
            ->first();

        if (!$enrollment) {
            return response()->json([
                'message' => 'You are not enrolled in this course'
            ], 403);
        }

        $publishedLessonCount = Lesson::where('course_id', $quiz->course_id)
            ->where('status', 'published')
            ->count();

        if ($publishedLessonCount > 0 && $enrollment->lessons_completed < $publishedLessonCount) {
            return response()->json([
                'message' => 'Complete all lessons before attempting final quiz'
            ], 403);
        }

        $request->validate([
            'answers' => 'required|array',
        ]);

        $earnedMarks = 0;
        $totalMarks = 0;
        $correctCount = 0;

        foreach ($quiz->questions as $index => $question) {
            $marks = max((int) $question->marks, 1);
            $totalMarks += $marks;
            $answer = null;
            $candidateKeys = [
                $question->id,
                (string) $question->id,
                $index + 1,
                (string) ($index + 1),
            ];

            foreach ($candidateKeys as $key) {
                if (array_key_exists($key, $request->answers)) {
                    $answer = $request->answers[$key];
                    break;
                }

                if (array_key_exists((string) $key, $request->answers)) {
                    $answer = $request->answers[(string) $key];
                    break;
                }
            }

            if (is_array($answer)) {
                $answer = $answer['value'] ?? $answer[0] ?? null;
            }

            $submittedAnswer = strtolower(trim((string) $answer));
            $storedAnswer = strtolower(trim((string) $question->correct_answer));
            $normalizedExpected = str_starts_with($storedAnswer, 'option_')
                ? substr($storedAnswer, 7)
                : $storedAnswer;
            $variants = [
                $storedAnswer,
                $normalizedExpected,
                'option_' . $normalizedExpected,
            ];

            if (in_array($submittedAnswer, $variants, true)) {
                $earnedMarks += $marks;
                $correctCount++;
            }
        }

        $percentage = $totalMarks > 0
            ? ($earnedMarks / $totalMarks) * 100
            : 0;

        $passed = $percentage >= $quiz->passing_percentage;
        $wasAlreadyCompleted = $enrollment->completed;
        $courseCompleted = $wasAlreadyCompleted || $passed;

        // Save final quiz result in enrollment
        $enrollment->update([
            'final_quiz_score' => $percentage,
            'final_quiz_passed' => $courseCompleted,
            'completed' => $courseCompleted,
            'progress' => $courseCompleted ? 100 : $enrollment->progress,
            'completed_at' => $courseCompleted
                ? ($enrollment->completed_at ?? now())
                : null,
        ]);

        if ($passed && !$wasAlreadyCompleted) {
            Notification::create([
                'user_id' => $request->user()->id,
                'title' => 'Course Completed',
                'message' => 'Congratulations! You have successfully completed the course.',
                'type' => 'course_completed',
                'related_id' => $quiz->course_id,
            ]);
        }

        return response()->json([
            'message' => 'Quiz submitted successfully',
            'total_questions' => $quiz->questions->count(),
            'correct_answers' => $correctCount,
            'earned_marks' => $earnedMarks,
            'total_marks' => $totalMarks,
            'score' => $percentage,
            'passing_percentage' => $quiz->passing_percentage,
            'passed' => $passed,
        ]);
    }
}
