<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Faculty;
use App\Models\User;
use App\Models\Evaluation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
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

    /**
     * Resolve the reporting period. Explicit ?semester / ?academic_year query
     * params win (plain strings, never validated against the Settings option
     * lists so archived/legacy values stay queryable); 'all' disables that
     * filter. Absent params fall back to the active period for BC.
     *
     * @return array{0: ?string, 1: ?string}
     */
    private function resolvePeriod(Request $request): array
    {
        $settings = \App\Models\Setting::cachedAll();
        $semester = $request->input('semester', $request->query('semester'));
        $academicYear = $request->input('academic_year', $request->query('academic_year'));
        if ($semester === null) {
            $semester = $settings->get('active_semester');
        }
        if ($academicYear === null) {
            $academicYear = $settings->get('active_academic_year');
        }
        if ($semester === 'all') {
            $semester = null;
        }
        if ($academicYear === 'all') {
            $academicYear = null;
        }
        if (is_string($semester)) {
            $semester = trim($semester);
            if ($semester === '') {
                $semester = null;
            }
        } elseif ($semester !== null) {
            $semester = (string) $semester;
        }
        if (is_string($academicYear)) {
            $academicYear = trim($academicYear);
            if ($academicYear === '') {
                $academicYear = null;
            }
        } elseif ($academicYear !== null) {
            $academicYear = (string) $academicYear;
        }
        // Settings value cast may return arrays for malformed rows; only strings are valid periods.
        if (!is_string($semester)) {
            $semester = null;
        }
        if (!is_string($academicYear)) {
            $academicYear = null;
        }
        return [$semester, $academicYear];
    }

    /**
     * Union of configured, archived, and actually-used academic periods.
     * Powers the dedicated archive page filters and the Settings "in use"
     * counts, so a semester/year removed from the active dropdowns can
     * still be reviewed through history.
     */
    public function academicPeriods()
    {
        $settings = \App\Models\Setting::cachedAll();
        $asList = function ($v) {
            if (is_array($v)) {
                return array_values(array_filter(array_map('trim', array_filter($v, 'is_string')), fn($s) => $s !== ''));
            }
            if (is_string($v) && trim($v) !== '') {
                $decoded = json_decode($v, true);
                if (is_array($decoded)) {
                    return array_values(array_filter(array_map('trim', array_filter($decoded, 'is_string')), fn($s) => $s !== ''));
                }
                return [trim($v)];
            }
            return [];
        };

        $semesters = $asList($settings->get('semester_options'));
        $archivedSemesters = $asList($settings->get('archived_semester_options'));
        $years = $asList($settings->get('academic_year_options'));
        $archivedYears = $asList($settings->get('archived_academic_year_options'));

        $used = DB::table('evaluations')
            ->select('semester', 'academic_year', DB::raw('COUNT(*) as evaluations_count'))
            ->whereNotNull('semester')->where('semester', '!=', '')
            ->whereNotNull('academic_year')->where('academic_year', '!=', '')
            ->groupBy('semester', 'academic_year')
            ->orderBy('academic_year', 'desc')
            ->orderBy('semester')
            ->get();

        $usedSemesters = [];
        $usedYears = [];
        $semesterUsage = [];
        $yearUsage = [];
        foreach ($used as $row) {
            if (!in_array($row->semester, $usedSemesters, true)) {
                $usedSemesters[] = $row->semester;
            }
            if (!in_array($row->academic_year, $usedYears, true)) {
                $usedYears[] = $row->academic_year;
            }
            $semesterUsage[$row->semester] = ($semesterUsage[$row->semester] ?? 0) + (int) $row->evaluations_count;
            $yearUsage[$row->academic_year] = ($yearUsage[$row->academic_year] ?? 0) + (int) $row->evaluations_count;
        }

        $merge = function (array ...$lists) {
            $out = [];
            foreach ($lists as $list) {
                foreach ($list as $v) {
                    if (!in_array($v, $out, true)) {
                        $out[] = $v;
                    }
                }
            }
            return $out;
        };

        return response()->json([
            'active_semester' => $settings->get('active_semester'),
            'active_academic_year' => $settings->get('active_academic_year'),
            'semesters' => $merge($semesters, $archivedSemesters, $usedSemesters),
            'academic_years' => $merge($years, $archivedYears, $usedYears),
            'archived_semesters' => $archivedSemesters,
            'archived_academic_years' => $archivedYears,
            'used_periods' => $used,
            'semester_usage' => $semesterUsage,
            'academic_year_usage' => $yearUsage,
        ]);
    }

    public function dashboardStats(Request $request)
    {
        $user = $request->user();
        $evaluateeType = $request->input('evaluatee_type', 'faculty');
        $ownEvaluatee = $this->resolveOwnEvaluatee($user);
        $scopedEvaluateeId = ($ownEvaluatee && $ownEvaluatee['type'] === $evaluateeType) ? $ownEvaluatee['id'] : null;

        $settings = \App\Models\Setting::cachedAll();
        [$activeSemester, $activeAcademicYear] = $this->resolvePeriod($request);

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
            ->unique(function ($item) {
                return $item->faculty_id ?: $item->evaluatee_id;
            })
            ->take(5)
            ->values();

        return response()->json([
            'total_faculty' => $scopedEvaluateeId ? 1 : Faculty::count(),
            'total_students' => $scopedEvaluateeId ? $totalEvaluations->distinct('student_id')->count() : User::where('role', 'student')->has('student')->count(),
            'total_evaluations' => $totalEvaluationsCount,
            'average_rating' => $avgQuery->avg('evaluation_answers.rating') ?: 0,
            'performance_overview' => $categoryAverages,
            'rating_distribution' => array_values($ratingsMap),
            'evaluatee_type' => $evaluateeType,
            'comments' => $comments
        ]);
    }

    public function facultySummary(Request $request)
    {
        // Explicit ?semester / ?academic_year win; absent params fall back to active period.
        [$activeSemester, $activeAcademicYear] = $this->resolvePeriod($request);

        $scores = DB::table('evaluation_answers')
            ->join('evaluations', 'evaluation_answers.evaluation_id', '=', 'evaluations.id')
            ->join('evaluation_questions', 'evaluation_answers.question_id', '=', 'evaluation_questions.id')
            ->join('evaluation_categories', 'evaluation_questions.category_id', '=', 'evaluation_categories.id')
            ->when($activeSemester, fn($q) => $q->where('evaluations.semester', $activeSemester))
            ->when($activeAcademicYear, fn($q) => $q->where('evaluations.academic_year', $activeAcademicYear))
            ->select(
                'evaluations.faculty_id',
                DB::raw('SUM(evaluation_answers.rating * evaluation_categories.weight / (SELECT COUNT(*) FROM evaluation_questions WHERE category_id = evaluation_categories.id)) as weighted_score')
            )
            ->whereNotNull('evaluations.faculty_id')
            ->groupBy('evaluations.faculty_id')
            ->pluck('weighted_score', 'faculty_id');

        $faculty = Faculty::with('user')->get();

        return response()->json($faculty->map(function ($f) use ($scores) {
            return [
                'id' => $f->id,
                'name' => $f->user->name,
                'department' => $f->department,
                'overall_score' => round($scores[$f->id] ?? 0, 2)
            ];
        })->values());
    }

    public function getEvaluateeDetailedReport(Request $request, $id)
    {
        return $this->getFacultyDetailedReport($request, $id);
    }

    public function getFacultyDetailedReport(Request $request, $facultyId)
    {
        $isAll = $facultyId === 'all';
        $departmentFilter = $request->query('department');
        
        // Explicit ?semester / ?academic_year win; absent params fall back to active period.
        [$activeSemester, $activeAcademicYear] = $this->resolvePeriod($request);

        if (!$isAll) {
            $faculty = Faculty::with('user')->findOrFail($facultyId);
            $facultyName = $faculty->user->name;
            $department = $faculty->department;
        } else {
            $facultyName = "All Faculty";
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

        // Filter by respondent year level. An explicitly tagged student matches
        // only their own year; students imported before year tracking (NULL
        // year_level) fall back to their section's year, and fully untagged
        // respondents still match every year.
        $yearLevelFilter = $request->query('year_level');
        if ($yearLevelFilter && $yearLevelFilter !== 'all') {
            $query->leftJoin('sections as respondent_sections', 'respondent_sections.id', '=', 'students.section_id');
            $query->where(function ($q) use ($yearLevelFilter) {
                $q->where('students.year_level', $yearLevelFilter)
                    ->orWhere(function ($q2) use ($yearLevelFilter) {
                        $q2->whereNull('students.year_level')
                            ->where(function ($q3) use ($yearLevelFilter) {
                                $q3->where('respondent_sections.year_level', $yearLevelFilter)
                                    ->orWhereNull('respondent_sections.year_level');
                            });
                    });
            });
        }

        // Double check filtering in main query if joinSub doesn't already cover it sufficiently for records
        if ($activeSemester) {
            $query->where('evaluations.semester', $activeSemester);
        }
        if ($activeAcademicYear) {
            $query->where('evaluations.academic_year', $activeAcademicYear);
        }

        // Respondents with no year on either their student record or their
        // section are untaggable by any year filter and match every year.
        // Counted here (same scope, before the year filter) so the UI can
        // explain why a year filter did not narrow the results.
        $untaggedRespondents = (clone $query)
            ->leftJoin('sections as untagged_sections', 'untagged_sections.id', '=', 'students.section_id')
            ->whereNull('students.year_level')
            ->whereNull('untagged_sections.year_level')
            ->distinct()
            ->count('evaluations.student_id');

        $groupedStats = $query->select(
                'students.course as student_course',
                'students.year_level as student_year',
                'evaluations.subject_code',
                'evaluations.year_section',
                DB::raw('COUNT(evaluations.student_id) as no_of_students'),
                DB::raw('ROUND(AVG(ea.avg_rating) * 20, 2) as average_set_rating')
            )
            ->groupBy('students.course', 'students.year_level', 'evaluations.subject_code', 'evaluations.year_section')
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
                'student_year' => $stat->student_year,
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
            'untagged_respondents' => $untaggedRespondents,
            'comments' => $comments
        ]);
    }

    public function getAiInsights(Request $request, $evaluateeId)
    {
        $departmentFilter = $request->query('department');
        $evaluateeType    = $request->input('evaluatee_type', 'faculty');

        // Explicit ?semester / ?academic_year win; absent params fall back to active period.
        [$activeSemester, $activeAcademicYear] = $this->resolvePeriod($request);

        // Build stats query
        $currentStatsQuery = DB::table('evaluation_answers')
            ->join('evaluations', 'evaluation_answers.evaluation_id', '=', 'evaluations.id')
            ->where('evaluations.evaluatee_type', $evaluateeType);

        $responseQuery = Evaluation::where('evaluatee_type', $evaluateeType);

        $currentStatsQuery->join('faculty', 'evaluations.faculty_id', '=', 'faculty.id');
        $responseQuery->join('faculty', 'evaluations.faculty_id', '=', 'faculty.id');
        if ($evaluateeId !== 'all') {
            $currentStatsQuery->where('evaluations.faculty_id', $evaluateeId);
            $responseQuery->where('evaluations.faculty_id', $evaluateeId);
        } elseif ($departmentFilter && $departmentFilter !== 'all') {
            $currentStatsQuery->where('faculty.department', $departmentFilter);
            $responseQuery->where('faculty.department', $departmentFilter);
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

        $commentQuery->join('faculty', 'evaluations.faculty_id', '=', 'faculty.id');
        if ($evaluateeId !== 'all') {
            $commentQuery->where('evaluations.faculty_id', $evaluateeId);
        } elseif ($departmentFilter && $departmentFilter !== 'all') {
            $commentQuery->where('faculty.department', $departmentFilter);
        }

        if ($activeSemester)    { $commentQuery->where('evaluations.semester', $activeSemester); }
        if ($activeAcademicYear){ $commentQuery->where('evaluations.academic_year', $activeAcademicYear); }

        $comments = $commentQuery->limit(200)->pluck('evaluations.comments')->toArray();

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

        // Cache successes: "all" aggregates are expensive and every modal open /
        // Try-Again click otherwise fires a fresh Gemini call into quota limits.
        $cacheKey = 'ai_insights:' . md5(json_encode([
            $evaluateeType, $evaluateeId, $departmentFilter,
            $activeSemester, $activeAcademicYear,
        ]));

        if ($request->boolean('refresh')) {
            Cache::forget($cacheKey);
        }

        $cached = Cache::get($cacheKey);
        if ($cached) {
            return response()->json($cached);
        }

        if (count($comments) > 50) {
            $comments = array_slice($comments, 0, 50);
        }

        $aiService = app(\App\Services\AiService::class);
        $insights  = $aiService->generateSummary($comments, $averageRating, $responseCount, null);

        if (!$insights) {
            $err = $aiService->lastError() ?? [];
            $code = $err['code'] ?? null;
            $status = $err['status'] ?? null;

            // Genuine quota exhaustion keeps the 429 contract the frontend expects.
            if ($code === 'quota_exceeded' || $status === 429) {
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

            // Timeouts, truncated JSON, model/config errors: 503, not 429.
            Log::warning('AI insights unavailable (non-quota).', [
                'code' => $code, 'status' => $status,
                'evaluatee_type' => $evaluateeType, 'evaluatee_id' => $evaluateeId,
            ]);

            return response()->json([
                'message'         => 'AI Analysis is temporarily unavailable. Please try again in a few minutes.',
                'overview'        => 'The AI service is temporarily unavailable.',
                'strengths'       => [],
                'issues'          => [],
                'recommendations' => [],
                'sentiment'       => ['positive' => 0, 'neutral' => 0, 'negative' => 0],
                'key_insights'    => 'Service unavailable.',
                'metric_insights' => [],
                'metrics'         => ['average_rating' => $averageRating, 'response_count' => $responseCount, 'previous_rating' => null]
            ], 503);
        }

        $insights['metrics'] = ['average_rating' => $averageRating, 'response_count' => $responseCount, 'previous_rating' => null];
        Cache::put($cacheKey, $insights, now()->addMinutes(10));
        return response()->json($insights);
    }

    public function myFeedback(Request $request)
    {
        $user = $request->user();
        if ($user->role !== 'faculty') {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $own = $this->resolveOwnEvaluatee($user);
        if (!$own) {
            return response()->json(['evaluatee_type' => $user->role, 'feedbacks' => []]);
        }

        $settings = \App\Models\Setting::cachedAll();
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

        $own = $this->resolveOwnEvaluatee($request->user());
        if ($own) {
            if ($own['type'] !== $evaluateeType) {
                return response()->json(['message' => 'Unauthorized.'], 403);
            }
            $request->merge(['faculty_id' => $own['id']]);
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
            ->paginate(min(max((int) ($request->per_page ?? 10), 1), 100));

        $facultyIds = $feedbacks->pluck('id')->all();

        $latestEvalQuery = Evaluation::whereIn('faculty_id', $facultyIds)
            ->whereNotNull('comments')->where('comments', '!=', '');
        if ($request->semester && $request->semester !== 'all') { $latestEvalQuery->where('semester', $request->semester); }
        if ($request->academic_year && $request->academic_year !== 'all') { $latestEvalQuery->where('academic_year', $request->academic_year); }
        if ($request->subject_code) { $latestEvalQuery->where('subject_code', 'LIKE', '%' . $request->subject_code . '%'); }

        $latestByFaculty = $latestEvalQuery
            ->select('faculty_id', 'comments', 'created_at')
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy('faculty_id')
            ->map(fn($items) => $items->first());

        $subjectsQuery = Evaluation::whereIn('faculty_id', $facultyIds)->whereNotNull('subject_code');
        if ($request->semester && $request->semester !== 'all') { $subjectsQuery->where('semester', $request->semester); }
        if ($request->academic_year && $request->academic_year !== 'all') { $subjectsQuery->where('academic_year', $request->academic_year); }

        $subjectsByFaculty = $subjectsQuery
            ->select('faculty_id', 'subject_code')
            ->distinct()
            ->get()
            ->groupBy('faculty_id')
            ->map(fn($items) => $items->pluck('subject_code')->toArray());

        foreach ($feedbacks as $feedback) {
            $latestEval = $latestByFaculty[$feedback->id] ?? null;
            $distinctSubjects = $subjectsByFaculty[$feedback->id] ?? [];

            $feedback->text         = $latestEval ? $latestEval->comments : '';
            $feedback->subject_code = count($distinctSubjects) > 1 ? count($distinctSubjects) . ' Subjects' : ($distinctSubjects[0] ?? 'N/A');
        }

        return response()->json($feedbacks);
    }

    public function getFeedbackDetail(Request $request, $id)
    {
        $own = $this->resolveOwnEvaluatee($request->user());
        if ($own) {
            if ($own['type'] !== 'faculty' || (string) $own['id'] !== (string) $id) {
                return response()->json(['message' => 'Unauthorized.'], 403);
            }
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
