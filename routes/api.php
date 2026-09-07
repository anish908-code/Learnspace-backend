<?php

use App\Http\Controllers\Api\Admin\CertificateController as AdminCertificateController;
use App\Http\Controllers\Api\Admin\CourseController as AdminCourseController;
use App\Http\Controllers\Api\Admin\LessonController as AdminLessonController;
use App\Http\Controllers\Api\Admin\NotificationController as AdminNotificationController;
use App\Http\Controllers\Api\Admin\ProjectController as AdminProjectController;
use App\Http\Controllers\Api\Admin\QuizController as AdminQuizController;
use App\Http\Controllers\Api\Admin\QuizQuestionController as AdminQuizQuestionController;
use App\Http\Controllers\Api\Admin\StudentController as AdminStudentController;
use App\Http\Controllers\Api\Admin\SubmissionController as AdminSubmissionController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PublicController;
use App\Http\Controllers\Api\SetupController;
use App\Http\Controllers\Api\UploadController;
use App\Http\Controllers\Api\Student\CertificateController as StudentCertificateController;
use App\Http\Controllers\Api\Student\CourseController as StudentCourseController;
use App\Http\Controllers\Api\Student\EnrollmentController as StudentEnrollmentController;
use App\Http\Controllers\Api\Student\LessonController as StudentLessonController;
use App\Http\Controllers\Api\Student\NotificationController as StudentNotificationController;
use App\Http\Controllers\Api\Student\ProjectController as StudentProjectController;
use App\Http\Controllers\Api\Student\QuizController as StudentQuizController;
use App\Http\Controllers\Api\Student\StudentController as StudentStudentController;
use App\Http\Controllers\Api\Student\SubmissionController as StudentSubmissionController;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;

Route::middleware([StartSession::class])->group(function (): void {
    Route::get('public/home', [PublicController::class, 'index']);
    Route::get('certificates/verify/{certificateCode}', [PublicController::class, 'verifyCertificate']);
    Route::get('setup/seed-users', [SetupController::class, 'seedUsers']);
    Route::post('setup/import-data', [SetupController::class, 'importData']);

    Route::prefix('auth')->group(function (): void {
        Route::post('register', [AuthController::class, 'register']);
        Route::post('login', [AuthController::class, 'login']);
        Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
        Route::post('reset-password', [AuthController::class, 'resetPassword']);
    });
});

Route::middleware([StartSession::class, 'sanctum.cookie', 'auth:sanctum'])->group(function (): void {
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::get('auth/user', [AuthController::class, 'user']);
    Route::post('auth/upload-image', [UploadController::class, 'uploadImage']);

    Route::prefix('student')
        ->middleware('role:student')
        ->group(function (): void {
            Route::get('courses', [StudentCourseController::class, 'index']);
            Route::get('courses/{id}', [StudentCourseController::class, 'show']);

            Route::get('enrollments', [StudentEnrollmentController::class, 'index']);
            Route::post('enrollments', [StudentEnrollmentController::class, 'store']);
            Route::get('enrollments/{id}', [StudentEnrollmentController::class, 'show']);
            Route::patch('enrollments/{id}', [StudentEnrollmentController::class, 'update']);

            Route::get('courses/{courseId}/lessons', [StudentLessonController::class, 'index']);
            Route::get('lessons/{id}', [StudentLessonController::class, 'show']);
            Route::post('lessons/{id}/complete', [StudentLessonController::class, 'markComplete']);

            Route::get('quizzes/{id}', [StudentQuizController::class, 'show']);
            Route::post('quizzes/{id}/submit', [StudentQuizController::class, 'submit']);

            Route::get('projects', [StudentProjectController::class, 'index']);
            Route::get('projects/{id}', [StudentProjectController::class, 'show']);

            Route::get('submissions', [StudentSubmissionController::class, 'index']);
            Route::post('submissions', [StudentSubmissionController::class, 'store']);
            Route::get('submissions/{id}', [StudentSubmissionController::class, 'show']);

            Route::get('certificates', [StudentCertificateController::class, 'index']);
            Route::get('certificates/{id}', [StudentCertificateController::class, 'show']);

            Route::get('notifications', [StudentNotificationController::class, 'index']);
            Route::get('notifications/{id}', [StudentNotificationController::class, 'show']);
            Route::patch('notifications/{id}/read', [StudentNotificationController::class, 'markAsRead']);
            Route::delete('notifications', [StudentNotificationController::class, 'clearAll']);

            Route::get('profile', [StudentStudentController::class, 'show']);
            Route::put('profile', [StudentStudentController::class, 'update']);
        });

    Route::prefix('admin')
        ->middleware('role:admin')
        ->group(function (): void {
            Route::get('students', [AdminStudentController::class, 'index']);
            Route::get('students/{id}', [AdminStudentController::class, 'show']);

            Route::get('courses', [AdminCourseController::class, 'index']);
            Route::post('courses', [AdminCourseController::class, 'store']);
            Route::get('courses/{id}', [AdminCourseController::class, 'show']);
            Route::put('courses/{id}', [AdminCourseController::class, 'update']);
            Route::delete('courses/{id}', [AdminCourseController::class, 'destroy']);

            Route::get('courses/{courseId}/lessons', [AdminLessonController::class, 'index']);
            Route::post('lessons', [AdminLessonController::class, 'store']);
            Route::get('lessons/{id}', [AdminLessonController::class, 'show']);
            Route::put('lessons/{id}', [AdminLessonController::class, 'update']);
            Route::delete('lessons/{id}', [AdminLessonController::class, 'destroy']);

            Route::get('quizzes', [AdminQuizController::class, 'index']);
            Route::post('quizzes', [AdminQuizController::class, 'store']);
            Route::get('quizzes/{id}', [AdminQuizController::class, 'show']);
            Route::put('quizzes/{id}', [AdminQuizController::class, 'update']);
            Route::delete('quizzes/{id}', [AdminQuizController::class, 'destroy']);

            Route::get('quizzes/{quizId}/questions', [AdminQuizQuestionController::class, 'index']);
            Route::post('quizzes/{quizId}/questions', [AdminQuizQuestionController::class, 'store']);
            Route::post('quizzes/{quizId}/questions/bulk', [AdminQuizQuestionController::class, 'bulkStore']);
            Route::get('quizzes/{quizId}/questions/{id}', [AdminQuizQuestionController::class, 'show']);
            Route::put('quizzes/{quizId}/questions/{id}', [AdminQuizQuestionController::class, 'update']);
            Route::delete('quizzes/{quizId}/questions/{id}', [AdminQuizQuestionController::class, 'destroy']);

            Route::get('projects', [AdminProjectController::class, 'index']);
            Route::post('projects', [AdminProjectController::class, 'store']);
            Route::get('projects/{id}', [AdminProjectController::class, 'show']);
            Route::put('projects/{id}', [AdminProjectController::class, 'update']);
            Route::delete('projects/{id}', [AdminProjectController::class, 'destroy']);

            Route::get('submissions', [AdminSubmissionController::class, 'index']);
            Route::get('submissions/{id}', [AdminSubmissionController::class, 'show']);
            Route::patch('submissions/{id}', [AdminSubmissionController::class, 'update']);

            Route::get('certificates', [AdminCertificateController::class, 'index']);
            Route::post('certificates', [AdminCertificateController::class, 'store']);
            Route::get('certificates/{id}', [AdminCertificateController::class, 'show']);
            Route::delete('certificates/{id}', [AdminCertificateController::class, 'destroy']);

            Route::get('notifications', [AdminNotificationController::class, 'index']);
            Route::get('notifications/{id}', [AdminNotificationController::class, 'show']);
            Route::patch('notifications/{id}/read', [AdminNotificationController::class, 'markAsRead']);

            Route::put('profile', [AuthController::class, 'updateProfile']);
        });
});
