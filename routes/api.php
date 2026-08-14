<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\GoogleAuthController;
use App\Http\Controllers\Api\FacultyController;
use App\Http\Controllers\Api\QuestionnaireController;
use App\Http\Controllers\Api\EvaluationController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SettingController;
use App\Http\Controllers\Api\CourseController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\UserRoleController;
use App\Http\Controllers\Api\AssignmentController;
use App\Http\Controllers\Api\AiController;
use App\Http\Controllers\Api\OfficeController;
use App\Http\Controllers\Api\OfficeFeedbackController;
use App\Http\Controllers\Api\QrCodeController;
use App\Http\Controllers\Api\OfficeReportController;
use App\Http\Controllers\Api\OfficeQuestionnaireController;

use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

// Google Auth Routes
Route::get('/auth/google', [GoogleAuthController::class, 'redirectToGoogle']);
Route::get('/auth/google/callback', [GoogleAuthController::class, 'handleGoogleCallback']);
Route::post('/auth/google/register', [GoogleAuthController::class, 'registerStudent']);
Route::post('/auth/google/link', [GoogleAuthController::class, 'linkGoogle'])->middleware('auth:sanctum');
Route::post('/auth/google/unlink', [GoogleAuthController::class, 'unlinkGoogle'])->middleware('auth:sanctum');
Route::post('/auth/google/unlink/{id}', [GoogleAuthController::class, 'unlinkGoogle'])->middleware(['auth:sanctum', 'permission:manage_users']);
Route::get('/courses', [CourseController::class, 'index']); // Public endpoint for registration form

// Public QR Code endpoint (no auth required for visitors)
Route::get('/qr/{token}', [QrCodeController::class, 'showByToken']);

// Public office feedback & questionnaire (no auth required for visitors scanning QR codes)
Route::post('office-feedback', [OfficeFeedbackController::class, 'submit']);
Route::get('office-categories', [OfficeQuestionnaireController::class, 'index']);
Route::get('office-categories/{categoryId}/questions', [OfficeQuestionnaireController::class, 'questions']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
    Route::put('/user/password', [AuthController::class, 'changePassword']);
    
    // System Settings
    Route::get('/settings', [SettingController::class, 'index']);
    Route::post('/settings', [SettingController::class, 'update']);

    // Faculty & Student Management (Admin)
    Route::get('faculty/all', [FacultyController::class, 'all'])->middleware('auth:sanctum');
    Route::get('students/all', [\App\Http\Controllers\Api\StudentController::class, 'all'])->middleware('auth:sanctum');
    Route::get('faculty', [FacultyController::class, 'index'])->middleware('permission:manage_faculty|view_reports');
    Route::middleware('permission:manage_users|manage_faculty')->group(function () {
        Route::post('faculty/import', [FacultyController::class, 'import']);
        Route::post('faculty/bulk-delete', [FacultyController::class, 'bulkDestroy']);
        Route::post('faculty/bulk-status', [FacultyController::class, 'bulkToggleActive']);
        Route::post('faculty', [FacultyController::class, 'store']);
        Route::put('faculty/{faculty}', [FacultyController::class, 'update']);
        Route::delete('faculty/{faculty}', [FacultyController::class, 'destroy']);
        Route::get('faculty/{faculty}', [FacultyController::class, 'show']);
        Route::patch('faculty/{id}/toggle-active', [FacultyController::class, 'toggleActive']);
        
        Route::post('students/import', [\App\Http\Controllers\Api\StudentController::class, 'import']);
        Route::post('students/bulk-delete', [\App\Http\Controllers\Api\StudentController::class, 'bulkDestroy']);
        Route::post('students/bulk-status', [\App\Http\Controllers\Api\StudentController::class, 'bulkToggleActive']);
        Route::apiResource('students', \App\Http\Controllers\Api\StudentController::class);
        Route::patch('students/{id}/toggle-active', [\App\Http\Controllers\Api\StudentController::class, 'toggleActive']);
        
        // Enrollments for irregular students
        Route::get('students/{studentId}/enrollments', [\App\Http\Controllers\Api\EnrollmentController::class, 'index']);
        Route::post('students/{studentId}/enrollments', [\App\Http\Controllers\Api\EnrollmentController::class, 'store']);
        Route::delete('enrollments/{id}', [\App\Http\Controllers\Api\EnrollmentController::class, 'destroy']);
    });

    // Course Management (Admin features inside auth)
    Route::middleware('permission:manage_courses')->group(function () {
        Route::post('courses', [CourseController::class, 'store']);
        Route::put('courses/{course}', [CourseController::class, 'update']);
        Route::delete('courses/{course}', [CourseController::class, 'destroy']);
    });

    // Faculty Assignments
    Route::middleware('permission:manage_faculty')->group(function () {
        Route::get('assignments', [AssignmentController::class, 'index']);
        Route::post('assignments', [AssignmentController::class, 'store']);
        Route::delete('assignments/{id}', [AssignmentController::class, 'destroy']);
        Route::get('assignments/meta', [AssignmentController::class, 'getMeta']);
    });

    // Questionnaire Management (Viewable by all auth users for evaluations)
    Route::get('categories', [QuestionnaireController::class, 'index']);
    Route::get('categories/{category}/questions', [QuestionnaireController::class, 'questions']);
    Route::get('questionnaire/stats', [QuestionnaireController::class, 'stats']);

    // Admin-only Questionnaire Management (Create/Update/Delete)
    Route::middleware('permission:manage_categories|manage_questions')->group(function () {
        Route::post('categories', [QuestionnaireController::class, 'store']); // assuming resource store
        Route::put('categories/{category}', [QuestionnaireController::class, 'update']);
        Route::delete('categories/{category}', [QuestionnaireController::class, 'destroy']);
        Route::post('questions', [QuestionnaireController::class, 'storeQuestion']);
        Route::put('questions/{question}', [QuestionnaireController::class, 'updateQuestion']);
        Route::delete('questions/{question}', [QuestionnaireController::class, 'destroyQuestion']);
    });

    // Evaluations
    Route::middleware('permission:give_evaluations')->group(function () {
        Route::get('evaluations/evaluatees', [EvaluationController::class, 'getEvaluatees']);
        Route::post('evaluations', [EvaluationController::class, 'store']);
    });
    Route::get('evaluations/results/{evaluateeId}', [EvaluationController::class, 'getResults']);

    // Reports
    Route::get('reports/dashboard', [ReportController::class, 'dashboardStats']);
    Route::get('reports/faculty-summary', [ReportController::class, 'facultySummary']);
    Route::get('reports/evaluatee/{id}', [ReportController::class, 'getEvaluateeDetailedReport']);
    Route::get('reports/ai-insights/{id}', [ReportController::class, 'getAiInsights']);
    Route::get('reports/my-feedback', [ReportController::class, 'myFeedback']);
    Route::get('reports/feedbacks', [ReportController::class, 'getFeedbacks']);
    Route::get('reports/feedbacks/{id}', [ReportController::class, 'getFeedbackDetail']);
    // Staff reports removed: staffSummary route deleted

    // RBAC Management & Backups
    Route::middleware('permission:manage_rbac')->group(function () {
        Route::get('permissions', [PermissionController::class, 'index']);
        Route::post('permissions', [PermissionController::class, 'store']);
        Route::delete('permissions/{permission}', [PermissionController::class, 'destroy']);
        
        Route::prefix('users/{user}')->group(function () {
            Route::post('roles', [UserRoleController::class, 'assignRole']);
            Route::post('permissions', [UserRoleController::class, 'assignPermission']);
            Route::get('rbac-details', [UserRoleController::class, 'getUserPermissions']);
        });

        // Backup & Restore
        Route::prefix('backups')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\BackupController::class, 'index']);
            Route::post('/', [\App\Http\Controllers\Api\BackupController::class, 'create']);
            Route::get('/download/{filename}', [\App\Http\Controllers\Api\BackupController::class, 'download']);
            Route::delete('/{filename}', [\App\Http\Controllers\Api\BackupController::class, 'delete']);
            Route::post('/restore', [\App\Http\Controllers\Api\BackupController::class, 'restore']);
            Route::post('/toggle-auto', [\App\Http\Controllers\Api\BackupController::class, 'toggleAutoBackup']);
        });
    });

    // AI Features
    Route::post('/ai/analyze-comment', [AiController::class, 'analyzeComment']);

    // Office Management (Admin)
    Route::get('offices/all', [OfficeController::class, 'all']);

    Route::middleware('permission:manage_offices|manage_faculty')->group(function () {
        Route::get('offices', [OfficeController::class, 'index']);
        Route::post('offices', [OfficeController::class, 'store']);
        Route::put('offices/{id}', [OfficeController::class, 'update']);
        Route::delete('offices/{id}', [OfficeController::class, 'destroy']);
        Route::patch('offices/{id}/toggle-active', [OfficeController::class, 'toggleActive']);
        Route::post('offices/bulk-delete', [OfficeController::class, 'bulkDestroy']);
        Route::post('offices/bulk-status', [OfficeController::class, 'bulkToggleActive']);
        Route::post('offices/{officeId}/personnel', [OfficeController::class, 'storePersonnel']);
        Route::delete('offices/{officeId}/personnel/{personnelId}', [OfficeController::class, 'destroyPersonnel']);

        // QR Code Management
        Route::get('qr-codes', [QrCodeController::class, 'index']);
        Route::post('qr-codes/generate', [QrCodeController::class, 'generate']);
        Route::post('qr-codes/{id}/regenerate', [QrCodeController::class, 'regenerate']);

        // Office Feedback Management
        Route::get('office-feedback', [OfficeFeedbackController::class, 'index']);
        Route::get('office-feedback/stats', [OfficeFeedbackController::class, 'stats']);
        Route::get('office-feedback/{id}', [OfficeFeedbackController::class, 'show']);
        Route::delete('office-feedback/{id}', [OfficeFeedbackController::class, 'destroy']);

        // Office Reports
        Route::get('office-reports/dashboard', [OfficeReportController::class, 'dashboardStats']);
        Route::get('office-reports/summary', [OfficeReportController::class, 'officeSummary']);
        Route::get('office-reports/{id}/feedbacks', [OfficeReportController::class, 'feedbacks']);
        Route::get('office-reports/{id}', [OfficeReportController::class, 'officeDetailedReport']);
        Route::get('office-reports/export/csv', [OfficeReportController::class, 'export']);

        // Office Questionnaire Management
        Route::post('office-categories', [OfficeQuestionnaireController::class, 'store']);
        Route::put('office-categories/{id}', [OfficeQuestionnaireController::class, 'update']);
        Route::delete('office-categories/{id}', [OfficeQuestionnaireController::class, 'destroy']);
        Route::get('office-categories/stats', [OfficeQuestionnaireController::class, 'stats']);
        Route::post('office-questions', [OfficeQuestionnaireController::class, 'storeQuestion']);
        Route::put('office-questions/{id}', [OfficeQuestionnaireController::class, 'updateQuestion']);
        Route::delete('office-questions/{id}', [OfficeQuestionnaireController::class, 'destroyQuestion']);
    });

    // Office detail (all authenticated users — students need this for evaluation form)
    Route::get('offices/{id}', [OfficeController::class, 'show']);
});

