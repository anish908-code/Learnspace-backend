<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Quiz;
use Illuminate\Http\Request;

class QuizController extends Controller
{
    // View all quizzes
    public function index(Request $request)
    {
        $perPage = min(max((int) $request->query('per_page', 50), 1), 100);

        $quizzes = Quiz::with('course')->paginate($perPage);

        return response()->json([
            'quizzes' => $quizzes->items(),
            'meta' => [
                'current_page' => $quizzes->currentPage(),
                'last_page' => $quizzes->lastPage(),
                'per_page' => $quizzes->perPage(),
                'total' => $quizzes->total(),
            ],
        ]);
    }

    // View single quiz with questions
    public function show($id)
    {
        $quiz = Quiz::with([
            'course',
            'questions'
        ])->findOrFail($id);

        return response()->json([
            'quiz' => $quiz
        ]);
    }

    // Create quiz
    public function store(Request $request)
    {
        $validated = $request->validate([
            'course_id' => 'required|exists:courses,id',
            'title' => 'required|string|max:255',
            'passing_percentage' => 'required|numeric|min:0|max:100',
            'status' => 'required|in:draft,published',
        ]);

        $quiz = Quiz::create($validated);

        return response()->json([
            'message' => 'Quiz created successfully',
            'quiz' => $quiz
        ], 201);
    }

    // Update quiz
    public function update(Request $request, $id)
    {
        $quiz = Quiz::findOrFail($id);

        $validated = $request->validate([
            'course_id' => 'sometimes|exists:courses,id',
            'title' => 'sometimes|string|max:255',
            'passing_percentage' => 'sometimes|numeric|min:0|max:100',
            'status' => 'sometimes|in:draft,published',
        ]);

        $quiz->update($validated);

        return response()->json([
            'message' => 'Quiz updated successfully',
            'quiz' => $quiz
        ]);
    }

    // Delete quiz
    public function destroy($id)
    {
        $quiz = Quiz::findOrFail($id);

        $quiz->delete();

        return response()->json([
            'message' => 'Quiz deleted successfully'
        ]);
    }
}
