<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lesson;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class LessonController extends Controller
{
    // View all lessons of a course
    public function index(Request $request, $courseId)
    {
        $perPage = min(max((int) $request->query('per_page', 50), 1), 100);

        $lessons = Lesson::where('course_id', $courseId)
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
    public function show($id)
    {
        $lesson = Lesson::findOrFail($id);

        return response()->json([
            'lesson' => $lesson
        ]);
    }

    protected function storeNotesFile(Request $request, ?string $existingPath = null)
    {
        if (!$request->hasFile('notes_file')) {
            return null;
        }

        $file = $request->file('notes_file');

        if ($file->getSize() > 4096 * 1024) {
            return response()->json(['message' => 'Notes file must be under 4MB'], 422);
        }

        $ext = strtolower($file->getClientOriginalExtension());
        if ($ext !== 'md') {
            return response()->json([
                'message' => 'Notes file must be a .md file',
            ], 422);
        }

        if ($existingPath && Storage::disk('local')->exists($existingPath)) {
            Storage::disk('local')->delete($existingPath);
        }

        return $file->store('lesson_notes', 'local');
    }

    // Create lesson
    public function store(Request $request)
    {
        $validated = $request->validate([
            'course_id' => 'required|exists:courses,id',
            'title' => 'required|string|max:255',
            'video_url' => 'nullable|url|max:255',
            'notes' => 'nullable|string',
            'lesson_order' => 'required|integer|min:1',
            'status' => 'required|in:draft,published',
        ]);

        $notesFile = $this->storeNotesFile($request);
        if ($notesFile instanceof \Illuminate\Http\JsonResponse) {
            return $notesFile;
        }

        $lesson = Lesson::create([
            'course_id' => $validated['course_id'],
            'title' => $validated['title'],
            'video_url' => $validated['video_url'] ?? null,
            'notes' => $request->input('notes') ?: null,
            'notes_file' => $notesFile,
            'lesson_order' => $validated['lesson_order'],
            'status' => $validated['status'],
        ]);

        return response()->json([
            'message' => 'Lesson created successfully',
            'lesson' => $lesson
        ], 201);
    }

    // Update lesson
    public function update(Request $request, $id)
    {
        $lesson = Lesson::findOrFail($id);

        $validated = $request->validate([
            'course_id' => 'sometimes|exists:courses,id',
            'title' => 'sometimes|string|max:255',
            'video_url' => 'nullable|url|max:255',
            'notes' => 'nullable|string',
            'lesson_order' => 'sometimes|integer|min:1',
            'status' => 'sometimes|in:draft,published',
        ]);

        if ($request->hasFile('notes_file')) {
            $notesFile = $this->storeNotesFile($request, $lesson->notes_file);
            if ($notesFile instanceof \Illuminate\Http\JsonResponse) {
                return $notesFile;
            }
        }

        $data = [
            'course_id' => $validated['course_id'] ?? $lesson->course_id,
            'title' => $validated['title'] ?? $lesson->title,
            'video_url' => array_key_exists('video_url', $validated)
                ? ($validated['video_url'] ?: null)
                : $lesson->video_url,
            'notes' => $request->has('notes') ? ($request->input('notes') ?: null) : $lesson->notes,
            'lesson_order' => $validated['lesson_order'] ?? $lesson->lesson_order,
            'status' => $validated['status'] ?? $lesson->status,
        ];

        if (isset($notesFile)) {
            $data['notes_file'] = $notesFile;
        }

        $lesson->update($data);

        return response()->json([
            'message' => 'Lesson updated successfully',
            'lesson' => $lesson
        ]);
    }

    // Delete lesson
    public function destroy($id)
    {
        $lesson = Lesson::findOrFail($id);

        if ($lesson->notes_file && Storage::disk('local')->exists($lesson->notes_file)) {
            Storage::disk('local')->delete($lesson->notes_file);
        }

        $lesson->delete();

        return response()->json([
            'message' => 'Lesson deleted successfully'
        ]);
    }
}
