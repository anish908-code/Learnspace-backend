<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Student;
use Illuminate\Http\Request;

class StudentController extends Controller
{
    // View all students
    public function index(Request $request)
    {
        $perPage = min(max((int) $request->query('per_page', 50), 1), 100);

        $students = Student::with('user')->paginate($perPage);

        return response()->json([
            'students' => $students->items(),
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

        return response()->json([
            'student' => $student
        ]);
    }
}
