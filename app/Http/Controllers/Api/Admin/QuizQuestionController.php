<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Quiz;
use App\Models\QuizQuestion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QuizQuestionController extends Controller
{
    // List all questions for a quiz
    public function index(Request $request, $quizId): JsonResponse
    {
        $perPage = min(max((int) $request->query('per_page', 50), 1), 100);

        $quiz = Quiz::findOrFail($quizId);
        $questions = $quiz->questions()->paginate($perPage);

        return response()->json([
            'quiz' => $quiz,
            'questions' => $questions->items(),
            'meta' => [
                'current_page' => $questions->currentPage(),
                'last_page' => $questions->lastPage(),
                'per_page' => $questions->perPage(),
                'total' => $questions->total(),
            ],
        ]);
    }

    // View single question
    public function show($quizId, $id): JsonResponse
    {
        Quiz::findOrFail($quizId);
        $question = QuizQuestion::where('quiz_id', $quizId)->findOrFail($id);

        return response()->json([
            'question' => $question,
        ]);
    }

    // Create a question for a quiz
    public function store(Request $request, $quizId): JsonResponse
    {
        $quiz = Quiz::findOrFail($quizId);

        $validated = $request->validate([
            'question'       => 'required|string',
            'option_a'       => 'required|string|max:255',
            'option_b'       => 'required|string|max:255',
            'option_c'       => 'required|string|max:255',
            'option_d'       => 'required|string|max:255',
            'correct_answer' => 'required|in:a,b,c,d',
            'marks'          => 'sometimes|integer|min:1',
        ]);

        $validated['quiz_id'] = $quiz->id;

        $question = QuizQuestion::create($validated);

        return response()->json([
            'message'  => 'Question created successfully',
            'question' => $question,
        ], 201);
    }

    // Update a question
    public function update(Request $request, $quizId, $id): JsonResponse
    {
        Quiz::findOrFail($quizId);
        $question = QuizQuestion::where('quiz_id', $quizId)->findOrFail($id);

        $validated = $request->validate([
            'question'       => 'sometimes|string',
            'option_a'       => 'sometimes|string|max:255',
            'option_b'       => 'sometimes|string|max:255',
            'option_c'       => 'sometimes|string|max:255',
            'option_d'       => 'sometimes|string|max:255',
            'correct_answer' => 'sometimes|in:a,b,c,d',
            'marks'          => 'sometimes|integer|min:1',
        ]);

        $question->update($validated);

        return response()->json([
            'message'  => 'Question updated successfully',
            'question' => $question,
        ]);
    }

    // Delete a question
    public function destroy($quizId, $id): JsonResponse
    {
        Quiz::findOrFail($quizId);
        $question = QuizQuestion::where('quiz_id', $quizId)->findOrFail($id);

        $question->delete();

        return response()->json([
            'message' => 'Question deleted successfully',
        ]);
    }

    // Bulk create questions for a quiz
    public function bulkStore(Request $request, $quizId): JsonResponse
    {
        $quiz = Quiz::findOrFail($quizId);

        $validated = $request->validate([
            'questions'                  => 'required|array|min:1',
            'questions.*.question'       => 'required|string',
            'questions.*.option_a'       => 'required|string|max:255',
            'questions.*.option_b'       => 'required|string|max:255',
            'questions.*.option_c'       => 'required|string|max:255',
            'questions.*.option_d'       => 'required|string|max:255',
            'questions.*.correct_answer' => 'required|in:a,b,c,d',
            'questions.*.marks'          => 'sometimes|integer|min:1',
        ]);

        $created = [];
        foreach ($validated['questions'] as $q) {
            $q['quiz_id'] = $quiz->id;
            $created[] = QuizQuestion::create($q);
        }

        return response()->json([
            'message'   => count($created) . ' questions created successfully',
            'questions' => $created,
        ], 201);
    }
}
