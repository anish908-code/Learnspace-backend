<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    // View all projects
    public function index(Request $request)
    {
        $perPage = min(max((int) $request->query('per_page', 50), 1), 100);

        $projects = Project::with('course')->paginate($perPage);

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

    // View single project
    public function show($id)
    {
        $project = Project::with('course')
            ->findOrFail($id);

        return response()->json([
            'project' => $project
        ]);
    }

    // Create project
    public function store(Request $request)
    {
        $validated = $request->validate([
            'course_id' => 'required|exists:courses,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'required_skills' => 'nullable|string|max:255',
            'difficulty' => 'nullable|string|max:50',
            'deadline' => 'nullable|date',
            'requirements' => 'nullable|string',
            'status' => 'required|in:draft,published',
        ]);

        $project = Project::create($validated);

        return response()->json([
            'message' => 'Project created successfully',
            'project' => $project
        ], 201);
    }

    // Update project
    public function update(Request $request, $id)
    {
        $project = Project::findOrFail($id);

        $validated = $request->validate([
            'course_id' => 'sometimes|exists:courses,id',
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'required_skills' => 'nullable|string|max:255',
            'difficulty' => 'nullable|string|max:50',
            'deadline' => 'nullable|date',
            'requirements' => 'nullable|string',
            'status' => 'sometimes|in:draft,published',
        ]);

        $project->update($validated);

        return response()->json([
            'message' => 'Project updated successfully',
            'project' => $project
        ]);
    }

    // Delete project
    public function destroy($id)
    {
        $project = Project::findOrFail($id);

        $project->delete();

        return response()->json([
            'message' => 'Project deleted successfully'
        ]);
    }
}
