<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use Illuminate\Http\Request;

class CertificateController extends Controller
{
    // Student apne certificates dekh sakta hai
    public function index(Request $request)
    {
        $user = $request->user();
        $student = $user->student;

        if (!$student) {
            return response()->json([
                'certificates' => [],
                'meta' => null,
            ]);
        }

        $perPage = min(max((int) $request->query('per_page', 50), 1), 100);

        $certificates = Certificate::with([
            'course',
            'project',
            'student.user'
        ])
            ->where('student_id', $student->id)
            ->latest('issue_date')
            ->paginate($perPage);

        return response()->json([
            'certificates' => $certificates->items(),
            'meta' => [
                'current_page' => $certificates->currentPage(),
                'last_page' => $certificates->lastPage(),
                'per_page' => $certificates->perPage(),
                'total' => $certificates->total(),
            ],
        ]);
    }

    // Single certificate dekhna
    public function show(Request $request, $id)
    {
        $user = $request->user();
        $student = $user->student;

        $certificate = Certificate::with([
            'course',
            'project',
            'student.user'
        ])
            ->where('id', $id)
            ->where('student_id', $student?->id)
            ->firstOrFail();

        return response()->json([
            'certificate' => $certificate
        ]);
    }
}
