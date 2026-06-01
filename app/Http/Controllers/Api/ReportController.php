<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Faculty;
use App\Models\Staff;
use App\Models\User;
use App\Models\Evaluation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReportController extends Controller
{
    private function resolveOwnEvaluatee(User $user): ?array
    {
        if ($user->role === 'faculty') {
            $faculty = Faculty::where('user_id', $user->id)->first();
            return $faculty ? ['id' => $faculty->id, 'type' => 'faculty'] : null;
        }
        if ($user->role === 'staff') {
            $staff = Staff::where('user_id', $user->id)->first();
            return $staff ? ['id' => $staff->id, 'type' => 'staff'] : null;
        }
        return null;
    }

    /** @param \Illuminate\Database\Query\Builder $query */
    private function applyFacultyEvaluationScope($query, string $facultyId, string $table = 'evaluations'): void
    {
        $prefix = $table . '.';
        $query->where(function ($q) use ($facultyId, $prefix) {
            $q->where($prefix . 'faculty_id', $facultyId)
                ->orWhere(function ($q2) use ($facultyId, $prefix) {
                    $q2->where($prefix . 'evaluatee_type', 'faculty')
                        ->where($prefix . 'evaluatee_id', $facultyId);
                });
        });
    }

    /** @param \Illuminate\Database\Query\Builder $query */
    private function applyScopedEvaluateeFilter($query, ?array $ownEvaluatee, string $evaluateeType, string $table = 'evaluations'): void
    {
        if (!$ownEvaluatee) {
            return;
        }

        if ($ownEvaluatee['type'] === 'faculty' && $evaluateeType === 'faculty') {
            $this->applyFacultyEvaluationScope($query, $ownEvaluatee['id'], $table);
            return;
        }

        $query->where($table . '.evaluatee_id', $ownEvaluatee['id']);
    }

    public function dashboardStats(Request $request)
    {
        $user = $request->user();
        $evaluateeType = $request->input('evaluatee_type', 'faculty');
        $ownEvaluatee = $this->resolveOwnEvaluatee($user);
        $scopedEvaluateeId = ($ownEvaluatee && $ownEvaluatee['type'] === $evaluateeType) ? $ownEvaluatee['id'] : null;

        $settings = \App\Models\Setting::all()->pluck('value', 'key');
        $activeSemester = $settings->get('active_semester');
        $activeAcademicYear = $settings->get('active_academic_year');

        $query = DB::table('evaluation_answers')
            ->join('evaluation_questions', 'evaluation_answers.question_id', '=', 'evaluation_questions.id')
            ->join('evaluation_categories', 'evaluation_questions.category_id', '=', 'evaluation_categories.id')
            ->join('evaluations', 'evaluation_answers.evaluation_id', '=', 'evaluations.id');

        // Filter by evaluatee type and categories
        $query->where('evaluations.evaluatee_type', $evaluateeType)
              ->where('evaluation_categories.evaluatee_type', $evaluateeType);

        $this->applyScopedEvaluateeFilter($query, $ownEvaluatee, $evaluateeType);

        if ($activeSemester) {
            $query->where('evaluations.semester', $activeSemester);
        }
        if ($activeAcademicYear) {
            $query->where('evaluations.academic_year', $activeAcademicYear);
        }

        $categoryAverages = $query->select(
                'evaluation_categories.category_name as label',
                DB::raw('ROUND(AVG(evaluation_answers.rating), 2) as average')
            )
            ->groupBy('evaluation_categories.id', 'evaluation_categories.category_name')
            ->get();

        $distQuery = DB::table('evaluation_answers')
            ->join('evaluations', 'evaluation_answers.evaluation_id', '=', 'evaluations.id')
            ->where('evaluations.evaluatee_type', $evaluateeType);

        $this->applyScopedEvaluateeFilter($distQuery, $ownEvaluatee, $evaluateeType);

        if ($activeSemester) {
            $distQuery->where('evaluations.semester', $activeSemester);
        }
        if ($activeAcademicYear) {
            $distQuery->where('evaluations.academic_year', $activeAcademicYear);
        }

        $ratingDistribution = $distQuery->select('evaluation_answers.rating', DB::raw('count(*) as count'))
            ->groupBy('evaluation_answers.rating')
            ->orderBy('evaluation_answers.rating', 'desc')
            ->get();

        $ratingsMap = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
        foreach ($ratingDistribution as $rd) {
            $ratingsMap[$rd->rating] = $rd->count;
        }

        $avgQuery = DB::table('evaluation_answers')
            ->join('evaluations', 'evaluation_answers.evaluation_id', '=', 'evaluations.id')
            ->where('evaluations.evaluatee_type', $evaluateeType);

        $this->applyScopedEvaluateeFilter($avgQuery, $ownEvaluatee, $evaluateeType);

        if ($activeSemester) {
            $avgQuery->where('evaluations.semester', $activeSemester);
        }
        if ($activeAcademicYear) {
            $avgQuery->where('evaluations.academic_year', $activeAcademicYear);
        }

        $totalEvaluations = Evaluation::query();
        if ($ownEvaluatee && $ownEvaluatee['type'] === $evaluateeType) {
            if ($ownEvaluatee['type'] === 'faculty') {
                $totalEvaluations->forFacultyMember($ownEvaluatee['id']);
            } else {
                $totalEvaluations->where('evaluatee_type', $evaluateeType)
                    ->where('evaluatee_id', $ownEvaluatee['id']);
            }
        } else {
            $totalEvaluations->where('evaluatee_type', $evaluateeType);
        }

        if ($activeSemester) {
            $totalEvaluations->where('semester', $activeSemester);
        }
        if ($activeAcademicYear) {
            $totalEvaluations->where('academic_year', $activeAcademicYear);
        }

        $totalEvaluationsCount = $totalEvaluations->count();

        // Get recent comments with their average rating for the dashboard feed
        $commentsQuery = DB::table('evaluations')
            ->leftJoin(DB::raw('(SELECT evaluation_id, AVG(rating) as avg_rating FROM evaluation_answers GROUP BY evaluation_id) as ea'), 'evaluations.id', '=', 'ea.evaluation_id')
            ->where('evaluations.evaluatee_type', $evaluateeType)
            ->whereNotNull('evaluations.comments')
            ->where('evaluations.comments', '!=', '');

        if ($evaluateeType === 'staff') {
            $commentsQuery->join('staff', 'evaluations.evaluatee_id', '=', 'staff.id')
                          ->join('users', 'staff.user_id', '=', 'users.id')
                          ->select(
                              'evaluations.evaluatee_id',
                              'evaluations.comments as text',
                              'users.name as faculty_name',
                              'ea.avg_rating as rating',
                              'evaluations.created_at'
                          );
        } else {
            $commentsQuery->join('faculty', 'evaluations.faculty_id', '=', 'faculty.id')
                          ->join('users', 'faculty.user_id', '=', 'users.id')
                          ->select(
                              'evaluations.evaluatee_id',
                              'evaluations.faculty_id',
                              'evaluations.comments as text',
                              'users.name as faculty_name',
                              'evaluations.subject_code',
                              'ea.avg_rating as rating',
                              'evaluations.created_at'
                          );
        }

        $this->applyScopedEvaluateeFilter($commentsQuery, $ownEvaluatee, $evaluateeType);

        if ($activeSemester) {
            $commentsQuery->where('evaluations.semester', $activeSemester);
        }
        if ($activeAcademicYear) {
            $commentsQuery->where('evaluations.academic_year', $activeAcademicYear);
        }

        // One feed item per evaluatee (most recent comment only)
        $comments = $commentsQuery
            ->orderBy('evaluations.created_at', 'desc')
            ->limit(50)
            ->get()
            ->unique(function ($item) use ($evaluateeType) {
                if ($evaluateeType === 'staff') {
                    return $item->evaluatee_id;
                }
                return $item->faculty_id ?: $item->evaluatee_id;
            })
            ->take(5)
            ->values();

        return response()->json([
            'total_faculty' => $scopedEvaluateeId ? 1 : ($evaluateeType === 'faculty' ? Faculty::count() : \App\Models\Staff::count()),
            'total_students' => $scopedEvaluateeId ? $totalEvaluations->distinct('student_id')->count() : User::where('role', 'student')->count(),
            'total_evaluations' => $totalEvaluationsCount,
            'average_rating' => $avgQuery->avg('evaluation_answers.rating') ?: 0,
            'performance_overview' => $categoryAverages,
            'rating_distribution' => array_values($ratingsMap),
            'evaluatee_type' => $evaluateeType,
            'comments' => $comments
        ]);
    }

    public function facultySummary()
    {
        // Fetch active period settings
        $settings = \App\Models\Setting::all()->pluck('value', 'key');
        $activeSemester = $settings->get('active_semester');
        $activeAcademicYear = $settings->get('active_academic_year');

        $faculty = Faculty::with('user')->get();
        $summary = [];

        foreach ($faculty as $f) {
            $query = DB::table('evaluation_answers')
                ->join('evaluations', 'evaluation_answers.evaluation_id', '=', 'evaluations.id')
                ->join('evaluation_questions', 'evaluation_answers.question_id', '=', 'evaluation_questions.id')
                ->join('evaluation_categories', 'evaluation_questions.category_id', '=', 'evaluation_categories.id')
                ->where('evaluations.faculty_id', $f->id);

            if ($activeSemester) {
                $query->where('evaluations.semester', $activeSemester);
            }
            if ($activeAcademicYear) {
                $query->where('evaluations.academic_year', $activeAcademicYear);
            }

            $results = $query->select(
                    DB::raw('SUM(evaluation_answers.rating * evaluation_categories.weight / (SELECT COUNT(*) FROM evaluation_questions WHERE category_id = evaluation_categories.id)) as weighted_score')
                )
                ->first();
            
            $summary[] = [
                'id' => $f->id,
                'name' => $f->user->name,
                'department' => $f->department,
                'overall_score' => round($results->weighted_score ?: 0, 2)
            ];
        }

        return response()->json($summary);
    }

    public function staffSummary()
    {
        $settings = \App\Models\Setting::all()->pluck('value', 'key');
        $activeSemester = $settings->get('active_semester');
        $activeAcademicYear = $settings->get('active_academic_year');

        $staffList = Staff::with('user')->get();
        $summary = [];

        foreach ($staffList as $s) {
            $query = DB::table('evaluation_answers')
                ->join('evaluations', 'evaluation_answers.evaluation_id', '=', 'evaluations.id')
                ->join('evaluation_questions', 'evaluation_answers.question_id', '=', 'evaluation_questions.id')
                ->join('evaluation_categories', 'evaluation_questions.category_id', '=', 'evaluation_categories.id')
                ->where('evaluations.evaluatee_id', $s->id)
                ->where('evaluations.evaluatee_type', 'staff');

            if ($activeSemester)    { $query->where('evaluations.semester', $activeSemester); }
            if ($activeAcademicYear){ $query->where('evaluations.academic_year', $activeAcademicYear); }

            $results = $query->select(
                DB::raw('SUM(evaluation_answers.rating * evaluation_categories.weight / (SELECT COUNT(*) FROM evaluation_questions WHERE category_id = evaluation_categories.id)) as weighted_score')
            )->first();

            $summary[] = [
                'id'            => $s->id,
                'user_id'       => $s->user_id,
                'name'          => $s->user->name ?? 'N/A',
                'department'    => $s->department,
                'designation'   => $s->designation,
                'overall_score' => round($results->weighted_score ?: 0, 2),
            ];
        }

        return response()->json($summary);
    }

    /** Unified router: dispatches to faculty or staff detailed report based on evaluatee_type query param */
    public function getEvaluateeDetailedReport(Request $request, $id)
    {
        $evaluateeType = $request->input('evaluatee_type', 'faculty');
        if ($evaluateeType === 'staff') {
            return $this->getStaffDetailedReport($request, $id);
        }
        return $this->getFacultyDetailedReport($request, $id);
    }

    public function getStaffDetailedReport(Request $request, $staffId)
    {
        $isAll = $staffId === 'all';
        $departmentFilter = $request->query('department');

        $settings = \App\Models\Setting::all()->pluck('value', 'key');
        $activeSemester    = $settings->get('active_semester');
        $activeAcademicYear = $settings->get('active_academic_year');

        if (!$isAll) {
            $staff = Staff::with('user')->findOrFail($staffId);
            $staffName  = $staff->user->name ?? 'N/A';
            $department = $staff->department;
        } else {
            $staffName  = 'All Staff';
            $department = ($departmentFilter && $departmentFilter !== 'all') ? $departmentFilter : 'All Departments';
        }

        $evalQuery = DB::table('evaluations')
            ->join('staff', 'evaluations.evaluatee_id', '=', 'staff.id')
            ->where('evaluations.evaluatee_type', 'staff');

        if (!$isAll) {
            $evalQuery->where('evaluations.evaluatee_id', $staffId);
        } elseif ($departmentFilter && $departmentFilter !== 'all') {
            $evalQuery->where('staff.department', $departmentFilter);
        }
        if ($activeSemester)    { $evalQuery->where('evaluations.semester', $activeSemester); }
        if ($activeAcademicYear){ $evalQuery->where('evaluations.academic_year', $activeAcademicYear); }

        $totalEvaluations  = $evalQuery->count('evaluations.id');
        $distinctEvaluators = (clone $evalQuery)->distinct()->count('evaluations.student_id');

        // Category scores
        $catQuery = DB::table('evaluation_answers')
            ->join('evaluations', 'evaluation_answers.evaluation_id', '=', 'evaluations.id')
            ->join('evaluation_questions', 'evaluation_answers.question_id', '=', 'evaluation_questions.id')
            ->join('evaluation_categories', 'evaluation_questions.category_id', '=', 'evaluation_categories.id')
            ->join('staff', 'evaluations.evaluatee_id', '=', 'staff.id')
            ->where('evaluations.evaluatee_type', 'staff')
            ->where('evaluation_categories.evaluatee_type', 'staff');

        if (!$isAll) {
            $catQuery->where('evaluations.evaluatee_id', $staffId);
        } elseif ($departmentFilter && $departmentFilter !== 'all') {
            $catQuery->where('staff.department', $departmentFilter);
        }
        if ($activeSemester)    { $catQuery->where('evaluations.semester', $activeSemester); }
        if ($activeAcademicYear){ $catQuery->where('evaluations.academic_year', $activeAcademicYear); }

        $categoryScores = $catQuery->select(
            'evaluation_categories.category_name',
            'evaluation_categories.weight',
            DB::raw('AVG(evaluation_answers.rating) as average_rating')
        )->groupBy('evaluation_categories.id', 'evaluation_categories.category_name', 'evaluation_categories.weight')
         ->get();

        $overallScore = $categoryScores->sum(fn($c) => $c->average_rating * $c->weight);

        // Comments
        $commentsQuery = DB::table('evaluations')
            ->join('staff', 'evaluations.evaluatee_id', '=', 'staff.id')
            ->join('users', 'staff.user_id', '=', 'users.id')
            ->where('evaluations.evaluatee_type', 'staff')
            ->whereNotNull('evaluations.comments')
            ->where('evaluations.comments', '!=', '');

        if (!$isAll) {
            $commentsQuery->where('evaluations.evaluatee_id', $staffId);
        } elseif ($departmentFilter && $departmentFilter !== 'all') {
            $commentsQuery->where('staff.department', $departmentFilter);
        }
        if ($activeSemester)    { $commentsQuery->where('evaluations.semester', $activeSemester); }
        if ($activeAcademicYear){ $commentsQuery->where('evaluations.academic_year', $activeAcademicYear); }

        $comments = $commentsQuery->select('evaluations.comments as text', 'users.name as staff_name')->get();

        return response()->json([
            'staff_name'          => $staffName,
            'faculty_name'        => $staffName, // alias for frontend compatibility
            'department'          => $department,
            'total_evaluations'   => $totalEvaluations,
            'total_evaluators'    => $distinctEvaluators,
            'total_students'      => $distinctEvaluators,
            'category_scores'     => $categoryScores,
            'course_summaries'    => [], // staff has no course breakdown
            'overall_set_rating'  => round($overallScore * 20, 2), // scale to 100
            'total_weighted_score'=> round($overallScore * 20, 2),
            'comments'            => $comments,
            'evaluatee_type'      => 'staff',
        ]);
    }

    public function getFacultyDetailedReport(Request $request, $facultyId)
    {
        $isAll = $facultyId === 'all';
        $departmentFilter = $request->query('department');
        
        // Fetch active period settings
        $settings = \App\Models\Setting::all()->pluck('value', 'key');
        $activeSemester = $settings->get('active_semester');
        $activeAcademicYear = $settings->get('active_academic_year');

        if (!$isAll) {
            $faculty = Faculty::with('user')->findOrFail($facultyId);
            $facultyName = $faculty->user->name;
            $department = $faculty->department;
        } else {
            $facultyName = "All Faculty and Staff";
            $department = ($departmentFilter && $departmentFilter !== 'all') ? $departmentFilter : "All Departments";
        }

        // Group evaluations by subject_code and year_section
        $evalAveragesQuery = DB::table('evaluation_answers')
            ->join('evaluations', 'evaluation_answers.evaluation_id', '=', 'evaluations.id');

        if ($activeSemester) {
            $evalAveragesQuery->where('evaluations.semester', $activeSemester);
        }
        if ($activeAcademicYear) {
            $evalAveragesQuery->where('evaluations.academic_year', $activeAcademicYear);
        }

        $evalAverages = $evalAveragesQuery->select('evaluation_id', DB::raw('AVG(rating) as avg_rating'))
            ->groupBy('evaluation_id');

        $query = DB::table('evaluations')
            ->joinSub($evalAverages, 'ea', function ($join) {
                $join->on('evaluations.id', '=', 'ea.evaluation_id');
            })
            ->join('users', 'evaluations.student_id', '=', 'users.id')
            ->join('students', 'users.id', '=', 'students.user_id')
            ->join('faculty', 'evaluations.faculty_id', '=', 'faculty.id')
            ->whereNotNull('evaluations.subject_code')
            ->whereNotNull('evaluations.year_section');

        if (!$isAll) {
            $query->where('evaluations.faculty_id', $facultyId);
        } elseif ($departmentFilter && $departmentFilter !== 'all') {
            $query->where('faculty.department', $departmentFilter);
        }

        // Double check filtering in main query if joinSub doesn't already cover it sufficiently for records
        if ($activeSemester) {
            $query->where('evaluations.semester', $activeSemester);
        }
        if ($activeAcademicYear) {
            $query->where('evaluations.academic_year', $activeAcademicYear);
        }

        $groupedStats = $query->select(
                'students.course as student_course',
                'evaluations.subject_code',
                'evaluations.year_section',
                DB::raw('COUNT(evaluations.student_id) as no_of_students'),
                DB::raw('ROUND(AVG(ea.avg_rating) * 20, 2) as average_set_rating') 
            )
            ->groupBy('students.course', 'evaluations.subject_code', 'evaluations.year_section')
            ->get();

        $courseSummaries = [];
        $totalStudents = 0;
        $totalWeightedScore = 0;

        foreach ($groupedStats as $stat) {
            $weightedScore = $stat->no_of_students * $stat->average_set_rating;
            $courseInfo = $stat->student_course ?? 'Unknown Course';
            
            if (!isset($courseSummaries[$courseInfo])) {
                $courseSummaries[$courseInfo] = [
                    'course_name' => $courseInfo,
                    'rows' => [],
                    'course_total_students' => 0,
                    'course_total_weighted_score' => 0,
                ];
            }
            
            $courseSummaries[$courseInfo]['rows'][] = [
                'course_code' => $stat->subject_code,
                'year_section' => $stat->year_section,
                'no_of_students' => $stat->no_of_students,
                'average_set_rating' => $stat->average_set_rating,
                'weighted_set_score' => $weightedScore
            ];
            
            $courseSummaries[$courseInfo]['course_total_students'] += $stat->no_of_students;
            $courseSummaries[$courseInfo]['course_total_weighted_score'] += $weightedScore;

            $totalStudents += $stat->no_of_students;
            $totalWeightedScore += $weightedScore;
        }

        // Calculate averages for each course
        foreach ($courseSummaries as &$summary) {
            $summary['course_average_rating'] = $summary['course_total_students'] > 0 
                ? round($summary['course_total_weighted_score'] / $summary['course_total_students'], 2)
                : 0;
        }
        unset($summary);

        $overallRating = $totalStudents > 0 ? round($totalWeightedScore / $totalStudents, 2) : 0;

        $commentsQuery = Evaluation::join('faculty', 'evaluations.faculty_id', '=', 'faculty.id')
            ->join('users', 'faculty.user_id', '=', 'users.id')
            ->whereNotNull('evaluations.comments')
            ->where('evaluations.comments', '!=', '');
            
        if (!$isAll) {
            $commentsQuery->where('evaluations.faculty_id', $facultyId);
        } elseif ($departmentFilter && $departmentFilter !== 'all') {
            $commentsQuery->where('faculty.department', $departmentFilter);
        }

        if ($activeSemester) {
            $commentsQuery->where('evaluations.semester', $activeSemester);
        }
        if ($activeAcademicYear) {
            $commentsQuery->where('evaluations.academic_year', $activeAcademicYear);
        }

        $comments = $commentsQuery->select(
                'evaluations.comments as text',
                'users.name as faculty_name'
            )
            ->get();

        return response()->json([
            'faculty_name' => $facultyName,
            'department' => $department,
            'course_summaries' => array_values($courseSummaries),
            'total_students' => $totalStudents,
            'total_weighted_score' => $totalWeightedScore,
            'overall_set_rating' => $overallRating,
            'comments' => $comments
        ]);
    }

    /**
     * Generate AI-powered insights for a specific faculty/staff member.
     * Supports evaluatee_type=faculty|staff query param.
     */
    public function getAiInsights(Request $request, $evaluateeId)
    {
        $departmentFilter = $request->query('department');
        $evaluateeType    = $request->input('evaluatee_type', 'faculty');
        $isStaff          = $evaluateeType === 'staff';

        $settings           = \App\Models\Setting::all()->pluck('value', 'key');
        $activeSemester     = $settings->get('active_semester');
        $activeAcademicYear = $settings->get('active_academic_year');

        // Build stats query
        $currentStatsQuery = DB::table('evaluation_answers')
            ->join('evaluations', 'evaluation_answers.evaluation_id', '=', 'evaluations.id')
            ->where('evaluations.evaluatee_type', $evaluateeType);

        $responseQuery = Evaluation::where('evaluatee_type', $evaluateeType);

        if ($isStaff) {
            $currentStatsQuery->join('staff', 'evaluations.evaluatee_id', '=', 'staff.id');
            if ($evaluateeId !== 'all') {
                $currentStatsQuery->where('evaluations.evaluatee_id', $evaluateeId);
                $responseQuery->where('evaluatee_id', $evaluateeId);
            } elseif ($departmentFilter && $departmentFilter !== 'all') {
                $currentStatsQuery->where('staff.department', $departmentFilter);
                $responseQuery->join('staff', 'evaluations.evaluatee_id', '=', 'staff.id')
                              ->where('staff.department', $departmentFilter);
            }
        } else {
            $currentStatsQuery->join('faculty', 'evaluations.faculty_id', '=', 'faculty.id');
            $responseQuery->join('faculty', 'evaluations.faculty_id', '=', 'faculty.id');
            if ($evaluateeId !== 'all') {
                $currentStatsQuery->where('evaluations.faculty_id', $evaluateeId);
                $responseQuery->where('evaluations.faculty_id', $evaluateeId);
            } elseif ($departmentFilter && $departmentFilter !== 'all') {
                $currentStatsQuery->where('faculty.department', $departmentFilter);
                $responseQuery->where('faculty.department', $departmentFilter);
            }
        }

        if ($activeSemester) {
            $currentStatsQuery->where('evaluations.semester', $activeSemester);
            $responseQuery->where('evaluations.semester', $activeSemester);
        }
        if ($activeAcademicYear) {
            $currentStatsQuery->where('evaluations.academic_year', $activeAcademicYear);
            $responseQuery->where('evaluations.academic_year', $activeAcademicYear);
        }

        $averageRating = round($currentStatsQuery->avg('evaluation_answers.rating') ?: 0, 2);
        $responseCount = $responseQuery->count();

        // Comments
        $commentQuery = Evaluation::where('evaluatee_type', $evaluateeType)
            ->whereNotNull('comments')
            ->where('comments', '!=', '');

        if ($isStaff) {
            if ($evaluateeId !== 'all') {
                $commentQuery->where('evaluatee_id', $evaluateeId);
            } elseif ($departmentFilter && $departmentFilter !== 'all') {
                $commentQuery->join('staff', 'evaluations.evaluatee_id', '=', 'staff.id')
                             ->where('staff.department', $departmentFilter);
            }
        } else {
            $commentQuery->join('faculty', 'evaluations.faculty_id', '=', 'faculty.id');
            if ($evaluateeId !== 'all') {
                $commentQuery->where('evaluations.faculty_id', $evaluateeId);
            } elseif ($departmentFilter && $departmentFilter !== 'all') {
                $commentQuery->where('faculty.department', $departmentFilter);
            }
        }

        if ($activeSemester)    { $commentQuery->where('evaluations.semester', $activeSemester); }
        if ($activeAcademicYear){ $commentQuery->where('evaluations.academic_year', $activeAcademicYear); }

        $comments = $commentQuery->pluck('evaluations.comments')->toArray();

        if (empty($comments)) {
            return response()->json([
                'overview'        => 'No qualitative feedback available to analyze.',
                'strengths'       => [],
                'issues'          => [],
                'recommendations' => [],
                'sentiment'       => ['positive' => 0, 'neutral' => 0, 'negative' => 0],
                'key_insights'    => 'Insufficient data.',
            ]);
        }

        if (count($comments) > 50) {
            $comments = array_slice($comments, 0, 50);
        }

        $aiService = app(\App\Services\AiService::class);
        $insights  = $aiService->generateSummary($comments, $averageRating, $responseCount, null);

        if (!$insights) {
            return response()->json([
                'message'         => 'AI Service is currently at its limit (Quota Exceeded). Please wait a few minutes and try again.',
                'overview'        => 'The AI service is temporarily unavailable due to high usage.',
                'strengths'       => [],
                'issues'          => [],
                'recommendations' => [],
                'sentiment'       => ['positive' => 0, 'neutral' => 0, 'negative' => 0],
                'key_insights'    => 'Quota reached.',
                'metric_insights' => [],
                'metrics'         => ['average_rating' => $averageRating, 'response_count' => $responseCount, 'previous_rating' => null]
            ], 429);
        }

        $insights['metrics'] = ['average_rating' => $averageRating, 'response_count' => $responseCount, 'previous_rating' => null];
        return response()->json($insights);
    }

    public function myFeedback(Request $request)
    {
        $user = $request->user();
        if (!in_array($user->role, ['faculty', 'staff'], true)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $own = $this->resolveOwnEvaluatee($user);
        if (!$own) {
            return response()->json(['evaluatee_type' => $user->role, 'feedbacks' => []]);
        }

        $settings = \App\Models\Setting::all()->pluck('value', 'key');
        $activeSemester = $settings->get('active_semester');
        $activeAcademicYear = $settings->get('active_academic_year');

        $semester = $request->input('semester');
        $academicYear = $request->input('academic_year');

        if ($semester === null && $activeSemester) {
            $semester = $activeSemester;
        }
        if ($academicYear === null && $activeAcademicYear) {
            $academicYear = $activeAcademicYear;
        }

        $query = DB::table('evaluations')
            ->leftJoin(DB::raw('(SELECT evaluation_id, AVG(rating) as avg_rating FROM evaluation_answers GROUP BY evaluation_id) as ea'), 'evaluations.id', '=', 'ea.evaluation_id')
            ->whereNotNull('evaluations.comments')
            ->where('evaluations.comments', '!=', '');

        if ($own['type'] === 'faculty') {
            $this->applyFacultyEvaluationScope($query, $own['id']);
        } else {
            $query->where('evaluations.evaluatee_type', $own['type'])
                ->where('evaluations.evaluatee_id', $own['id']);
        }

        if ($semester && $semester !== 'all') {
            $query->where('evaluations.semester', $semester);
        }
        if ($academicYear && $academicYear !== 'all') {
            $query->where('evaluations.academic_year', $academicYear);
        }

        $feedbacks = $query->select(
                'evaluations.id',
                'evaluations.comments as text',
                'evaluations.subject_code',
                'evaluations.year_section',
                'evaluations.semester',
                'evaluations.academic_year',
                'evaluations.created_at',
                'ea.avg_rating as rating'
            )
            ->orderBy('evaluations.created_at', 'desc')
            ->get();

        return response()->json([
            'evaluatee_type' => $own['type'],
            'feedbacks'      => $feedbacks,
        ]);
    }

    public function getFeedbacks(Request $request)
    {
        $evaluateeType = $request->input('evaluatee_type', 'faculty');
        $isStaff = $evaluateeType === 'staff';

        $own = $this->resolveOwnEvaluatee($request->user());
        if ($own) {
            if ($own['type'] !== $evaluateeType) {
                return response()->json(['message' => 'Unauthorized.'], 403);
            }
            if ($isStaff) {
                $request->merge(['evaluatee_id' => $own['id']]);
            } else {
                $request->merge(['faculty_id' => $own['id']]);
            }
        }

        if ($isStaff) {
            // Staff feedback query
            $query = Evaluation::join('staff', 'evaluations.evaluatee_id', '=', 'staff.id')
                ->join('users', 'staff.user_id', '=', 'users.id')
                ->leftJoin(DB::raw('(SELECT evaluation_id, AVG(rating) as avg_rating FROM evaluation_answers GROUP BY evaluation_id) as ea'), 'evaluations.id', '=', 'ea.evaluation_id')
                ->where('evaluations.evaluatee_type', 'staff')
                ->whereNotNull('evaluations.comments')
                ->where('evaluations.comments', '!=', '');

            if ($request->evaluatee_id && $request->evaluatee_id !== 'all') {
                $query->where('evaluations.evaluatee_id', $request->evaluatee_id);
            }
            if ($request->semester && $request->semester !== 'all') {
                $query->where('evaluations.semester', $request->semester);
            }
            if ($request->academic_year && $request->academic_year !== 'all') {
                $query->where('evaluations.academic_year', $request->academic_year);
            }
            if ($request->department && $request->department !== 'all') {
                $query->where('staff.department', $request->department);
            }
            if ($request->search) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('users.name', 'LIKE', '%' . $search . '%')
                      ->orWhere('evaluations.comments', 'LIKE', '%' . $search . '%');
                });
            }
            if ($request->rating && $request->rating !== 'all') {
                $query->where(DB::raw('ROUND(ea.avg_rating, 0)'), (int)$request->rating);
            }

            $feedbacks = $query->select(
                    'staff.id as id',
                    'users.name as faculty_name',
                    'staff.department',
                    'staff.designation',
                    DB::raw('MAX(evaluations.created_at) as created_at'),
                    DB::raw('ROUND(AVG(ea.avg_rating), 2) as rating')
                )
                ->groupBy('staff.id', 'users.name', 'staff.department', 'staff.designation')
                ->orderBy('created_at', 'desc')
                ->paginate($request->per_page ?? 10);

            foreach ($feedbacks as $feedback) {
                $latestEval = Evaluation::where('evaluatee_id', $feedback->id)
                    ->where('evaluatee_type', 'staff')
                    ->whereNotNull('comments')->where('comments', '!='  , '')
                    ->orderBy('created_at', 'desc')->first();
                $feedback->text         = $latestEval ? $latestEval->comments : '';
                $feedback->subject_code = 'N/A';
            }

            return response()->json($feedbacks);
        }

        // Faculty feedback query (original logic)
        $query = Evaluation::join('faculty', 'evaluations.faculty_id', '=', 'faculty.id')
            ->join('users', 'faculty.user_id', '=', 'users.id')
            ->leftJoin(DB::raw('(SELECT evaluation_id, AVG(rating) as avg_rating FROM evaluation_answers GROUP BY evaluation_id) as ea'), 'evaluations.id', '=', 'ea.evaluation_id')
            ->where('evaluations.evaluatee_type', 'faculty')
            ->whereNotNull('evaluations.comments')
            ->where('evaluations.comments', '!=', '');

        if ($request->faculty_id && $request->faculty_id !== 'all') {
            $query->where('evaluations.faculty_id', $request->faculty_id);
        }
        if ($request->semester && $request->semester !== 'all') {
            $query->where('evaluations.semester', $request->semester);
        }
        if ($request->academic_year && $request->academic_year !== 'all') {
            $query->where('evaluations.academic_year', $request->academic_year);
        }
        if ($request->department && $request->department !== 'all') {
            $query->where('faculty.department', $request->department);
        }
        if ($request->subject_code) {
            $query->where('evaluations.subject_code', 'LIKE', '%' . $request->subject_code . '%');
        }
        if ($request->search) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('users.name', 'LIKE', '%' . $search . '%')
                  ->orWhere('evaluations.comments', 'LIKE', '%' . $search . '%')
                  ->orWhere('evaluations.subject_code', 'LIKE', '%' . $search . '%');
            });
        }
        if ($request->rating && $request->rating !== 'all') {
            $query->where(DB::raw('ROUND(ea.avg_rating, 0)'), (int)$request->rating);
        }

        $feedbacks = $query->select(
                'faculty.id as id',
                'users.name as faculty_name',
                'faculty.department',
                DB::raw('MAX(evaluations.created_at) as created_at'),
                DB::raw('ROUND(AVG(ea.avg_rating), 2) as rating')
            )
            ->groupBy('faculty.id', 'users.name', 'faculty.department')
            ->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 10);

        foreach ($feedbacks as $feedback) {
            $latestEvalQuery = Evaluation::where('faculty_id', $feedback->id)
                ->whereNotNull('comments')->where('comments', '!=', '');
            if ($request->semester && $request->semester !== 'all') { $latestEvalQuery->where('semester', $request->semester); }
            if ($request->academic_year && $request->academic_year !== 'all') { $latestEvalQuery->where('academic_year', $request->academic_year); }
            if ($request->subject_code) { $latestEvalQuery->where('subject_code', 'LIKE', '%' . $request->subject_code . '%'); }

            $latestEval = $latestEvalQuery->orderBy('created_at', 'desc')->first();

            $subjectsQuery = Evaluation::where('faculty_id', $feedback->id)->whereNotNull('subject_code');
            if ($request->semester && $request->semester !== 'all') { $subjectsQuery->where('semester', $request->semester); }
            if ($request->academic_year && $request->academic_year !== 'all') { $subjectsQuery->where('academic_year', $request->academic_year); }

            $distinctSubjects       = $subjectsQuery->distinct()->pluck('subject_code')->toArray();
            $feedback->text         = $latestEval ? $latestEval->comments : '';
            $feedback->subject_code = count($distinctSubjects) > 1 ? count($distinctSubjects) . ' Subjects' : ($distinctSubjects[0] ?? 'N/A');
        }

        return response()->json($feedbacks);
    }

    public function getFeedbackDetail(Request $request, $id)
    {
        $evaluateeType = $request->input('evaluatee_type', 'faculty');
        $isStaff = $evaluateeType === 'staff';

        $own = $this->resolveOwnEvaluatee($request->user());
        if ($own) {
            if ($own['type'] !== $evaluateeType || (string) $own['id'] !== (string) $id) {
                return response()->json(['message' => 'Unauthorized.'], 403);
            }
        }

        if ($isStaff) {
            $staff = Staff::with('user')->findOrFail($id);

            $query = Evaluation::query()
                ->where('evaluatee_id', $id)
                ->where('evaluatee_type', 'staff')
                ->whereNotNull('comments')
                ->where('comments', '!=', '');

            if ($request->semester && $request->semester !== 'all')         { $query->where('semester', $request->semester); }
            if ($request->academic_year && $request->academic_year !== 'all'){ $query->where('academic_year', $request->academic_year); }

            $evaluations = $query->orderBy('created_at', 'desc')->get()
                ->makeHidden(['student_id']);

            if ($evaluations->isEmpty()) {
                return response()->json([
                    'faculty'         => $staff,
                    'evaluatee_type'  => 'staff',
                    'evaluations'     => [],
                    'category_scores' => [],
                    'overall_rating'  => 0,
                ]);
            }

            $evaluationIds = $evaluations->pluck('id');
            $answers = DB::table('evaluation_answers')
                ->join('evaluation_questions', 'evaluation_answers.question_id', '=', 'evaluation_questions.id')
                ->join('evaluation_categories', 'evaluation_questions.category_id', '=', 'evaluation_categories.id')
                ->whereIn('evaluation_answers.evaluation_id', $evaluationIds)
                ->select('evaluation_categories.category_name', DB::raw('AVG(evaluation_answers.rating) as average_rating'))
                ->groupBy('evaluation_categories.id', 'evaluation_categories.category_name')
                ->get();

            $overallRating = DB::table('evaluation_answers')->whereIn('evaluation_id', $evaluationIds)->avg('rating');

            return response()->json([
                'faculty'         => $staff,
                'evaluatee_type'  => 'staff',
                'evaluations'     => $evaluations,
                'category_scores' => $answers,
                'overall_rating'  => round((float)$overallRating, 2),
            ]);
        }

        // Faculty (original logic)
        $faculty = Faculty::with('user')->findOrFail($id);

        $query = Evaluation::query()->where('faculty_id', $id)
            ->whereNotNull('comments')
            ->where('comments', '!=', '');

        if ($request->semester && $request->semester !== 'all')          { $query->where('semester', $request->semester); }
        if ($request->academic_year && $request->academic_year !== 'all') { $query->where('academic_year', $request->academic_year); }
        if ($request->subject_code) { $query->where('subject_code', 'LIKE', '%' . $request->subject_code . '%'); }

        if (!$own) {
            $query->with('student');
        }

        $evaluations = $query->orderBy('created_at', 'desc')->get();
        if ($own) {
            $evaluations->makeHidden(['student_id']);
        }

        if ($evaluations->isEmpty()) {
            return response()->json([
                'faculty'         => $faculty,
                'evaluatee_type'  => 'faculty',
                'evaluations'     => [],
                'category_scores' => [],
                'overall_rating'  => 0,
            ]);
        }

        $evaluationIds = $evaluations->pluck('id');
        $answers = DB::table('evaluation_answers')
            ->join('evaluation_questions', 'evaluation_answers.question_id', '=', 'evaluation_questions.id')
            ->join('evaluation_categories', 'evaluation_questions.category_id', '=', 'evaluation_categories.id')
            ->whereIn('evaluation_answers.evaluation_id', $evaluationIds)
            ->select('evaluation_categories.category_name', DB::raw('AVG(evaluation_answers.rating) as average_rating'))
            ->groupBy('evaluation_categories.id', 'evaluation_categories.category_name')
            ->get();

        $overallRating = DB::table('evaluation_answers')->whereIn('evaluation_id', $evaluationIds)->avg('rating');

        return response()->json([
            'faculty'         => $faculty,
            'evaluatee_type'  => 'faculty',
            'evaluations'     => $evaluations,
            'category_scores' => $answers,
            'overall_rating'  => round((float)$overallRating, 2),
        ]);
    }
}
