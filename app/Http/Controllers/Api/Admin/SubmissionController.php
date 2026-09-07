<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\Submission;
use Illuminate\Http\Request;

class SubmissionController extends Controller
{
    // Admin all submissions dekh sakta hai
    public function index(Request $request)
    {
        $perPage = min(max((int) $request->query('per_page', 50), 1), 100);

        $submissions = Submission::with([
            'student.user',
            'project'
        ])->paginate($perPage);

        return response()->json([
            'submissions' => $submissions->items(),
            'meta' => [
                'current_page' => $submissions->currentPage(),
                'last_page' => $submissions->lastPage(),
                'per_page' => $submissions->perPage(),
                'total' => $submissions->total(),
            ],
        ]);
    }

    // Admin single submission dekh sakta hai
    public function show($id)
    {
        $submission = Submission::with([
            'student.user',
            'project'
        ])->findOrFail($id);

        return response()->json([
            'submission' => $submission
        ]);
    }

    // Admin submission review karega
    public function update(Request $request, $id)
    {
        $submission = Submission::findOrFail($id);

        $validated = $request->validate([
            'status' => 'required|in:pending,under_review,approved,rejected,changes_required',
            'feedback' => 'nullable|string',
        ]);

        $submission->update([
            'status' => $validated['status'],
            'feedback' => $validated['feedback'] ?? null,
            'reviewed_at' => in_array($validated['status'], ['approved', 'rejected', 'changes_required'], true)
                ? now()
                : null,
        ]);

        Notification::create([
            'user_id' => $submission->student->user_id,
            'title' => 'Project Review Updated',
            'message' => 'Your project submission status is now: ' . str_replace('_', ' ', $validated['status']) . '.',
            'type' => 'submission_review',
            'related_id' => $submission->id,
        ]);

        return response()->json([
            'message' => 'Submission reviewed successfully',
            'submission' => $submission->load([
                'student.user',
                'project'
            ])
        ]);
    }
}
