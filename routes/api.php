<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\GoogleAuthController;
use App\Http\Controllers\Api\FacultyController;
use App\Http\Controllers\Api\QuestionnaireController;
use App\Http\Controllers\Api\EvaluationController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SettingController;
use App\Http\Controllers\Api\EvaluationScheduleController;
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
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\LoginLogController;

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API routes
|--------------------------------------------------------------------------
| Authorization: every protected operation is enforced HERE on the backend
| via the fail-closed `permission:` middleware (default deny -> 403). The
| frontend only derives UI visibility from the same capability names —
| hiding a button is never treated as security.
|
| Permission names come from config/authorization.php (catalog).
*/

Route::post('/login', [AuthController::class, 'login']);

// Google Auth Routes
Route::get('/auth/google', [GoogleAuthController::class, 'redirectToGoogle']);
Route::get('/auth/google/callback', [GoogleAuthController::class, 'handleGoogleCallback']);
Route::post('/auth/google/register', [GoogleAuthController::class, 'registerStudent']);
Route::post('/auth/google/link', [GoogleAuthController::class, 'linkGoogle'])->middleware('auth:sanctum');
Route::post('/auth/google/unlink', [GoogleAuthController::class, 'unlinkGoogle'])->middleware('auth:sanctum');
Route::post('/auth/google/unlink/{id}', [GoogleAuthController::class, 'unlinkGoogle'])->middleware(['auth:sanctum', 'permission:user.edit']);
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

    // System Settings (write is privileged; read is needed by every flow)
    Route::get('/settings', [SettingController::class, 'index']);
    Route::post('/settings', [SettingController::class, 'update'])->middleware('permission:settings.manage');

    // Evaluation scheduling — per department windows plus one "All departments"
    // default row. Read (status) is needed by students; management is admin-only.
    Route::get('/evaluation-schedules/status', [EvaluationScheduleController::class, 'status']);
    Route::get('/evaluation-schedules', [EvaluationScheduleController::class, 'index'])->middleware('permission:settings.manage');
    Route::post('/evaluation-schedules', [EvaluationScheduleController::class, 'store'])->middleware('permission:settings.manage');
    Route::put('/evaluation-schedules/{schedule}', [EvaluationScheduleController::class, 'update'])->middleware('permission:settings.manage');
    Route::delete('/evaluation-schedules/{schedule}', [EvaluationScheduleController::class, 'destroy'])->middleware('permission:settings.manage');

    // Faculty & Student Management
    Route::get('faculty/all', [FacultyController::class, 'all'])->middleware('auth:sanctum');
    Route::get('students/all', [\App\Http\Controllers\Api\StudentController::class, 'all'])->middleware('auth:sanctum');
    Route::get('faculty', [FacultyController::class, 'index'])->middleware('permission:faculty.view');
    Route::get('faculty/export', [FacultyController::class, 'export'])->middleware('permission:faculty.view');

    Route::post('faculty/import', [FacultyController::class, 'import'])->middleware('permission:faculty.import');
    Route::post('faculty/bulk-delete', [FacultyController::class, 'bulkDestroy'])->middleware('permission:faculty.delete');
    Route::post('faculty/bulk-status', [FacultyController::class, 'bulkToggleActive'])->middleware('permission:faculty.edit');
    Route::post('faculty', [FacultyController::class, 'store'])->middleware('permission:faculty.create');
    Route::put('faculty/{faculty}', [FacultyController::class, 'update'])->middleware('permission:faculty.edit');
    Route::delete('faculty/{faculty}', [FacultyController::class, 'destroy'])->middleware('permission:faculty.delete');
    Route::get('faculty/{faculty}', [FacultyController::class, 'show'])->middleware('permission:faculty.view');
    Route::patch('faculty/{id}/toggle-active', [FacultyController::class, 'toggleActive'])->middleware('permission:faculty.edit');

    // Students
    Route::post('students/import', [\App\Http\Controllers\Api\StudentController::class, 'import'])->middleware('permission:student.import');
    Route::post('students/bulk-delete', [\App\Http\Controllers\Api\StudentController::class, 'bulkDestroy'])->middleware('permission:student.delete');
    Route::post('students/bulk-status', [\App\Http\Controllers\Api\StudentController::class, 'bulkToggleActive'])->middleware('permission:student.edit');
    Route::get('students', [\App\Http\Controllers\Api\StudentController::class, 'index'])->middleware('permission:student.view');
    Route::get('students/export', [\App\Http\Controllers\Api\StudentController::class, 'export'])->middleware('permission:student.view');
    Route::post('students', [\App\Http\Controllers\Api\StudentController::class, 'store'])->middleware('permission:student.create');
    Route::get('students/{student}', [\App\Http\Controllers\Api\StudentController::class, 'show'])->middleware('permission:student.view');
    Route::put('students/{student}', [\App\Http\Controllers\Api\StudentController::class, 'update'])->middleware('permission:student.edit');
    Route::patch('students/{student}', [\App\Http\Controllers\Api\StudentController::class, 'update'])->middleware('permission:student.edit');
    Route::delete('students/{student}', [\App\Http\Controllers\Api\StudentController::class, 'destroy'])->middleware('permission:student.delete');
    Route::patch('students/{id}/toggle-active', [\App\Http\Controllers\Api\StudentController::class, 'toggleActive'])->middleware('permission:student.edit');

    // Enrollments for irregular students
    Route::get('students/{studentId}/enrollments', [\App\Http\Controllers\Api\EnrollmentController::class, 'index'])->middleware('permission:student.view');
    Route::post('students/{studentId}/enrollments', [\App\Http\Controllers\Api\EnrollmentController::class, 'store'])->middleware('permission:student.edit');
    Route::delete('enrollments/{id}', [\App\Http\Controllers\Api\EnrollmentController::class, 'destroy'])->middleware('permission:student.edit');

    // Course Management
    Route::post('courses', [CourseController::class, 'store'])->middleware('permission:course.create');
    Route::put('courses/{course}', [CourseController::class, 'update'])->middleware('permission:course.edit');
    Route::delete('courses/{course}', [CourseController::class, 'destroy'])->middleware('permission:course.delete');
    Route::post('courses/{course}/subjects', [CourseController::class, 'storeSubject'])->middleware('permission:course.edit');
    Route::post('courses/{course}/subjects/import', [CourseController::class, 'importSubjects'])->middleware('permission:course.import');
    Route::post('courses/{course}/subjects/bulk-delete', [CourseController::class, 'bulkDestroySubjects'])->middleware('permission:course.delete');
    Route::post('courses/{course}/sections', [CourseController::class, 'storeSection'])->middleware('permission:course.edit');
    Route::delete('courses/{course}/subjects/{subject}', [CourseController::class, 'destroySubject'])->middleware('permission:course.delete');
    Route::delete('courses/{course}/sections/{section}', [CourseController::class, 'destroySection'])->middleware('permission:course.delete');

    // Faculty Assignments
    Route::get('assignments', [AssignmentController::class, 'index'])->middleware('permission:assignment.view');
    Route::post('assignments', [AssignmentController::class, 'store'])->middleware('permission:assignment.create');
    Route::delete('assignments/{id}', [AssignmentController::class, 'destroy'])->middleware('permission:assignment.delete');
    Route::get('assignments/meta', [AssignmentController::class, 'getMeta'])->middleware('permission:assignment.view');

    // Questionnaire (read) — needed by every evaluation flow, authenticated only
    Route::get('categories', [QuestionnaireController::class, 'index']);
    Route::get('categories/{category}/questions', [QuestionnaireController::class, 'questions']);
    Route::get('questionnaire/stats', [QuestionnaireController::class, 'stats']);

    // Questionnaire Management (Create/Update/Delete)
    Route::post('categories', [QuestionnaireController::class, 'store'])->middleware('permission:questionnaire.create');
    Route::put('categories/{category}', [QuestionnaireController::class, 'update'])->middleware('permission:questionnaire.edit');
    Route::delete('categories/{category}', [QuestionnaireController::class, 'destroy'])->middleware('permission:questionnaire.delete');
    Route::post('questions', [QuestionnaireController::class, 'storeQuestion'])->middleware('permission:questionnaire.create');
    Route::put('questions/{question}', [QuestionnaireController::class, 'updateQuestion'])->middleware('permission:questionnaire.edit');
    Route::delete('questions/{question}', [QuestionnaireController::class, 'destroyQuestion'])->middleware('permission:questionnaire.delete');

    // Evaluations
    Route::get('evaluations/evaluatees', [EvaluationController::class, 'getEvaluatees'])->middleware('permission:evaluation.create');
    Route::post('evaluations', [EvaluationController::class, 'store'])->middleware('permission:evaluation.submit');
    // Data scope (own/team/all) is enforced inside the handler.
    Route::get('evaluations/results/{evaluateeId}', [EvaluationController::class, 'getResults'])->middleware('permission:evaluation.view');

    // Reports (data scope enforced inside handlers; periods are metadata)
    Route::get('reports/dashboard', [ReportController::class, 'dashboardStats'])->middleware('permission:dashboard.view');
    // Summary status: evaluation activity + online students (aggregates only).
    Route::get('dashboard/status', [DashboardController::class, 'status'])->middleware('permission:dashboard.view');
    Route::get('reports/periods', [ReportController::class, 'academicPeriods']);
    Route::get('reports/faculty-summary', [ReportController::class, 'facultySummary'])->middleware('permission:report.view');
    Route::get('reports/evaluatee/{id}', [ReportController::class, 'getEvaluateeDetailedReport'])->middleware('permission:report.view');
    Route::get('reports/ai-insights/{id}', [ReportController::class, 'getAiInsights'])->middleware('permission:report.view');
    Route::get('reports/my-feedback',      [ReportController::class, 'myFeedback'])->middleware('permission:report.view');
    Route::get('reports/my-subject-ratings', [ReportController::class, 'mySubjectRatings'])->middleware('permission:report.view');
    Route::get('reports/feedbacks', [ReportController::class, 'getFeedbacks'])->middleware('permission:report.view');
    Route::get('reports/feedbacks/{id}', [ReportController::class, 'getFeedbackDetail'])->middleware('permission:report.view');
    // Staff reports removed: staffSummary route deleted

    // Role management (granular RBAC)
    Route::get('roles', [RoleController::class, 'index'])->middleware('permission:role.view');
    Route::post('roles', [RoleController::class, 'store'])->middleware('permission:role.create');
    Route::get('roles/{role}', [RoleController::class, 'show'])->middleware('permission:role.view');
    Route::put('roles/{role}', [RoleController::class, 'update'])->middleware('permission:role.edit');
    Route::delete('roles/{role}', [RoleController::class, 'destroy'])->middleware('permission:role.delete');
    Route::put('roles/{role}/permissions', [RoleController::class, 'assignPermissions'])->middleware('permission:permission.manage');

    // Permission catalog & audit log
    Route::get('permissions', [PermissionController::class, 'index'])->middleware('permission:permission.view');
    Route::post('permissions', [PermissionController::class, 'store'])->middleware('permission:permission.manage');
    Route::delete('permissions/{permission}', [PermissionController::class, 'destroy'])->middleware('permission:permission.manage');
    Route::get('audit-logs', [AuditLogController::class, 'index'])->middleware('permission:permission.manage');
    Route::get('audit-logs/actions', [AuditLogController::class, 'actions'])->middleware('permission:permission.manage');
    // Account access log (login successes, failures, lockouts)
    Route::get('auth/login-logs', [LoginLogController::class, 'index'])->middleware('permission:permission.manage');

    // Per-user role/permission assignment
    Route::prefix('users/{user}')->middleware('permission:permission.manage')->group(function () {
        Route::post('roles', [UserRoleController::class, 'assignRole']);
        Route::post('permissions', [UserRoleController::class, 'assignPermission']);
        Route::get('rbac-details', [UserRoleController::class, 'getUserPermissions']);
    });

    // Backup & Restore
    Route::prefix('backups')->middleware('permission:backup.manage')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\BackupController::class, 'index']);
        Route::post('/', [\App\Http\Controllers\Api\BackupController::class, 'create']);
        Route::get('/download/{filename}', [\App\Http\Controllers\Api\BackupController::class, 'download']);
        Route::delete('/{filename}', [\App\Http\Controllers\Api\BackupController::class, 'delete']);
        Route::post('/restore', [\App\Http\Controllers\Api\BackupController::class, 'restore']);
        Route::post('/upload', [\App\Http\Controllers\Api\BackupController::class, 'upload']);
        Route::post('/toggle-auto', [\App\Http\Controllers\Api\BackupController::class, 'toggleAutoBackup']);
    });

    // AI Features (used by the evaluation form while submitting)
    Route::post('/ai/analyze-comment', [AiController::class, 'analyzeComment'])->middleware('permission:evaluation.submit');

    // Office Management
    Route::get('offices/all', [OfficeController::class, 'all']);

    Route::get('offices', [OfficeController::class, 'index'])->middleware('permission:office.view');
    Route::post('offices', [OfficeController::class, 'store'])->middleware('permission:office.create');
    Route::put('offices/{id}', [OfficeController::class, 'update'])->middleware('permission:office.edit');
    Route::delete('offices/{id}', [OfficeController::class, 'destroy'])->middleware('permission:office.delete');
    Route::patch('offices/{id}/toggle-active', [OfficeController::class, 'toggleActive'])->middleware('permission:office.edit');
    Route::post('offices/bulk-delete', [OfficeController::class, 'bulkDestroy'])->middleware('permission:office.delete');
    Route::post('offices/bulk-status', [OfficeController::class, 'bulkToggleActive'])->middleware('permission:office.edit');
    Route::post('offices/{officeId}/personnel', [OfficeController::class, 'storePersonnel'])->middleware('permission:office.edit');
    Route::delete('offices/{officeId}/personnel/{personnelId}', [OfficeController::class, 'destroyPersonnel'])->middleware('permission:office.edit');

    // QR Code Management
    Route::get('qr-codes', [QrCodeController::class, 'index'])->middleware('permission:office.view');
    Route::post('qr-codes/generate', [QrCodeController::class, 'generate'])->middleware('permission:office.qr.manage');
    Route::post('qr-codes/{id}/regenerate', [QrCodeController::class, 'regenerate'])->middleware('permission:office.qr.manage');

    // Office Feedback Management
    Route::get('office-feedback', [OfficeFeedbackController::class, 'index'])->middleware('permission:office.feedback.view');
    Route::get('office-feedback/stats', [OfficeFeedbackController::class, 'stats'])->middleware('permission:office.feedback.view');
    Route::get('office-feedback/{id}', [OfficeFeedbackController::class, 'show'])->middleware('permission:office.feedback.view');
    Route::delete('office-feedback/{id}', [OfficeFeedbackController::class, 'destroy'])->middleware('permission:office.feedback.delete');

    // Office Reports
    Route::get('office-reports/dashboard', [OfficeReportController::class, 'dashboardStats'])->middleware('permission:office.report.view');
    Route::get('office-reports/summary', [OfficeReportController::class, 'officeSummary'])->middleware('permission:office.report.view');
    Route::get('office-reports/{id}/feedbacks', [OfficeReportController::class, 'feedbacks'])->middleware('permission:office.report.view');
    Route::get('office-reports/{id}', [OfficeReportController::class, 'officeDetailedReport'])->middleware('permission:office.report.view');
    Route::get('office-reports/export/csv', [OfficeReportController::class, 'export'])->middleware('permission:office.report.export');

    // Office Questionnaire Management
    Route::post('office-categories', [OfficeQuestionnaireController::class, 'store'])->middleware('permission:office.questionnaire.create');
    Route::put('office-categories/{id}', [OfficeQuestionnaireController::class, 'update'])->middleware('permission:office.questionnaire.edit');
    Route::delete('office-categories/{id}', [OfficeQuestionnaireController::class, 'destroy'])->middleware('permission:office.questionnaire.delete');
    Route::get('office-categories/stats', [OfficeQuestionnaireController::class, 'stats'])->middleware('permission:office.view');
    Route::post('office-questions', [OfficeQuestionnaireController::class, 'storeQuestion'])->middleware('permission:office.questionnaire.create');
    Route::put('office-questions/{id}', [OfficeQuestionnaireController::class, 'updateQuestion'])->middleware('permission:office.questionnaire.edit');
    Route::delete('office-questions/{id}', [OfficeQuestionnaireController::class, 'destroyQuestion'])->middleware('permission:office.questionnaire.delete');

    // Office detail (all authenticated users — students need this for evaluation form)
    Route::get('offices/{id}', [OfficeController::class, 'show']);
});
