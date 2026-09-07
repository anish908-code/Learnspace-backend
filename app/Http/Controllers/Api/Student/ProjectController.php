<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Models\Project;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    // View all available projects
    public function index(Request $request)
    {
        $student = $request->user()->student;
        $perPage = min(max((int) $request->query('per_page', 50), 1), 100);

        $completedCourseIds = Enrollment::where('student_id', $student->id)
            ->where('completed', true)
            ->where('final_quiz_passed', true)
            ->pluck('course_id');

        $projects = Project::with('course')
            ->where('status', 'published')
            ->whereIn('course_id', $completedCourseIds)
            ->paginate($perPage);

        return response()->json([
            'projects' => $projects->items(),
            'meta' => [
                'current_page' => $projects->currentPage(),
                'last_page' => $projects->lastPage(),
                'per_page' => $projects->perPage(),
                'total' => $projects->total(),
            ],
        ]);
    }

    // View single project details
    public function show(Request $request, $id)
    {
        $student = $request->user()->student;
        $project = Project::with('course')
            ->where('status', 'published')
            ->findOrFail($id);

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

        return response()->json([
            'project' => $project
        ]);
    }
}
