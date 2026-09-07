<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Models\Notification;
use App\Models\Project;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Http\Request;

class SubmissionController extends Controller
{
    // Student apni submissions dekh sakta hai
    public function index(Request $request)
    {
        $student = $request->user()->student;
        $perPage = min(max((int) $request->query('per_page', 50), 1), 100);

        $submissions = Submission::with('project')
            ->where('student_id', $student->id)
            ->paginate($perPage);

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

    // Student ek submission ki details dekh sakta hai
    public function show(Request $request, $id)
    {
        $student = $request->user()->student;

        $submission = Submission::with('project')
            ->where('student_id', $student->id)
            ->findOrFail($id);

        return response()->json([
            'submission' => $submission
        ]);
    }

    // Student project submit karega
    public function store(Request $request)
    {
        $student = $request->user()->student;

        $validated = $request->validate([
            'project_id' => 'required|exists:projects,id',
            'github_link' => 'required|url|max:255',
            'live_demo' => 'nullable|url|max:255',
            'documentation' => 'nullable|string',
            'description' => 'nullable|string',
        ]);

        $project = Project::where('status', 'published')
            ->findOrFail($validated['project_id']);

        $isUnlocked = Enrollment::where('student_id', $student->id)
            ->where('course_id', $project->course_id)
            ->where('completed', true)
            ->where('final_quiz_passed', true)
            ->exists();

        if (!$isUnlocked) {
            return response()->json([
                'message' => 'Project is locked until course completion and final quiz pass'
            ], 403);
        }

        $pendingReviewExists = Submission::where('student_id', $student->id)
            ->where('project_id', $project->id)
            ->whereIn('status', ['pending', 'under_review'])
            ->exists();

        if ($pendingReviewExists) {
            return response()->json([
                'message' => 'An existing submission is already pending review'
            ], 409);
        }

        $submission = Submission::create([
            'student_id' => $student->id,
            'project_id' => $project->id,
            'github_link' => $validated['github_link'],
            'live_demo' => $validated['live_demo'] ?? null,
            'documentation' => $validated['documentation'] ?? null,
            'description' => $validated['description'] ?? null,
            'status' => 'pending',
            'feedback' => null,
            'submitted_at' => now(),
            'reviewed_at' => null,
        ]);

        $adminUsers = User::where('role', 'admin')->pluck('id');
        foreach ($adminUsers as $adminId) {
            Notification::create([
                'user_id' => $adminId,
                'title' => 'New Project Submission',
                'message' => 'A student submitted a project for review.',
                'type' => 'submission',
                'related_id' => $submission->id,
            ]);
        }

        return response()->json([
            'message' => 'Project submitted successfully',
            'submission' => $submission->load('project')
        ], 201);
    }
}
