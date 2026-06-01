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
use App\Http\Controllers\Api\StaffController;

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

        // Staff Management
        Route::post('staff/bulk-delete', [StaffController::class, 'bulkDestroy']);
        Route::post('staff/bulk-status', [StaffController::class, 'bulkToggleActive']);
        Route::get('staff', [StaffController::class, 'index']);
        Route::post('staff', [StaffController::class, 'store']);
        Route::get('staff/{id}', [StaffController::class, 'show']);
        Route::put('staff/{id}', [StaffController::class, 'update']);
        Route::delete('staff/{id}', [StaffController::class, 'destroy']);
        Route::patch('staff/{id}/toggle-active', [StaffController::class, 'toggleActive']);
    });

    // Course Management (Admin features inside auth)
    Route::middleware('permission:manage_courses')->group(function () {
        Route::post('courses', [CourseController::class, 'store']);
        Route::put('courses/{course}', [CourseController::class, 'update']);
        Route::delete('courses/{course}', [CourseController::class, 'destroy']);
        Route::get('courses/{course}', [CourseController::class, 'show']);
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
        Route::get('evaluations/faculty', [EvaluationController::class, 'getFacultyToEvaluate']);
        Route::get('evaluations/evaluatees', [EvaluationController::class, 'getEvaluatees']);
        Route::post('evaluations', [EvaluationController::class, 'store']);
    });
    Route::get('evaluations/results/{facultyId}', [EvaluationController::class, 'getResults']);
    Route::get('evaluations/results-by-type', [EvaluationController::class, 'getResults']);

    // Reports
    Route::get('reports/dashboard', [ReportController::class, 'dashboardStats']);
    Route::get('reports/faculty-summary', [ReportController::class, 'facultySummary']);
    Route::get('reports/faculty/{id}', [ReportController::class, 'getFacultyDetailedReport']);
    Route::get('reports/staff/{id}', [ReportController::class, 'getStaffDetailedReport']);
    Route::get('reports/evaluatee/{id}', [ReportController::class, 'getEvaluateeDetailedReport']);
    Route::get('reports/ai-insights/{id}', [ReportController::class, 'getAiInsights']);
    Route::get('reports/my-feedback', [ReportController::class, 'myFeedback']);
    Route::get('reports/feedbacks', [ReportController::class, 'getFeedbacks']);
    Route::get('reports/feedbacks/{id}', [ReportController::class, 'getFeedbackDetail']);
    Route::get('reports/staff-list', [ReportController::class, 'staffSummary']);

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
});

