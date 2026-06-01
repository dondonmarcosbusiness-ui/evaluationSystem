<?php

namespace App\Http\Controllers\Api;

use App\Enums\EvaluateeType;
use App\Http\Controllers\Controller;
use App\Models\Answer;
use App\Models\Evaluation;
use App\Models\Faculty;
use App\Models\FacultyAssignment;
use App\Models\Staff;
use App\Rules\EvaluateeExists;
use App\Rules\ValidEvaluateeType;
use App\Services\EvaluationAnswerRepairService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EvaluationController extends Controller
{
    protected $aiService;

    public function __construct(\App\Services\AiService $aiService)
    {
        $this->aiService = $aiService;
    }

    public function getEvaluatees(Request $request)
    {
        try {
            $user = $request->user();
            $evaluateeType = $request->input('evaluatee_type', 'faculty');

            if (!$user || ($user->role === 'student' && !$user->student)) {
                return response()->json([]);
            }

            $settings = \App\Models\Setting::all()->pluck('value', 'key');
            $activeSemester = $settings->get('active_semester');
            $activeAcademicYear = $settings->get('active_academic_year');
            $evaluationStatus = $settings->get('evaluation_status', 'closed');

            if ($evaluationStatus !== 'open') {
                return response()->json([]);
            }

            if ($evaluateeType === EvaluateeType::STAFF->value) {
                return $this->getStaffToEvaluate($user, $activeSemester, $activeAcademicYear);
            }

            // Default to faculty
            return $this->getFacultyToEvaluateNew($user, $activeSemester, $activeAcademicYear);
        } catch (\Exception $e) {
            Log::error('getEvaluatees error: ' . $e->getMessage());
            return response()->json(['message' => 'Error loading evaluatees list'], 500);
        }
    }

    private function getStaffToEvaluate($user, $activeSemester, $activeAcademicYear)
    {
        $staffList = Staff::with('user')->get()->map(function ($staff) use ($user, $activeSemester, $activeAcademicYear) {
            // Check if already evaluated
            $isEvaluated = Evaluation::where([
                'student_id' => $user->id,
                'evaluatee_id' => $staff->id,
                'evaluatee_type' => 'staff',
                'semester' => $activeSemester,
                'academic_year' => $activeAcademicYear
            ])->exists();

            return [
                'id' => $staff->id,
                'evaluatee_type' => 'staff',
                'type' => 'staff',
                'user' => $staff->user,
                'department' => $staff->department,
                'designation' => $staff->designation,
                'is_evaluated' => $isEvaluated,
            ];
        });

        return response()->json($staffList->values());
    }

    private function getFacultyToEvaluateNew($user, $activeSemester, $activeAcademicYear)
    {
        $user->load('student.section_relationship.course');
        $studentSection = $user->student ? $user->student->section_relationship : null;
        $studentCourseName = $studentSection ? ($studentSection->course->name ?? null) : null;
        $sectionId = $user->student ? $user->student->section_id : null;
        $studentType = $user->student ? $user->student->student_type : 'regular';

        $facultyDataMap = [];
        $evaluatedFacultyIds = [];
        if ($activeSemester && $activeAcademicYear) {
            $evaluatedFacultyIds = Evaluation::where('student_id', $user->id)
                ->where('evaluatee_type', 'faculty')
                ->where('semester', $activeSemester)
                ->where('academic_year', $activeAcademicYear)
                ->pluck('evaluatee_id')
                ->toArray();
        }

        if ($studentType === 'irregular') {
            $enrollments = \App\Models\Enrollment::with(['instructor.user', 'subject'])
                ->where('student_id', $user->id)
                ->when($activeSemester, fn($q) => $q->where('semester', $activeSemester))
                ->when($activeAcademicYear, fn($q) => $q->where('academic_year', $activeAcademicYear))
                ->get();

            foreach ($enrollments as $enrollment) {
                $f = $enrollment->instructor;
                if (!$f) continue;

                $subjectCode = $enrollment->subject->code ?? $enrollment->subject->name;

                if (isset($facultyDataMap[$f->id])) {
                    $facultyDataMap[$f->id]['subject_name'] .= ', ' . $enrollment->subject->name;
                    $facultyDataMap[$f->id]['subject_code'] .= ', ' . $subjectCode;
                    continue;
                }

                $facultyDataMap[$f->id] = [
                    'id' => $f->id,
                    'evaluatee_type' => 'faculty',
                    'type' => 'faculty',
                    'assignment_id' => 'enroll-' . $enrollment->id,
                    'user' => $f->user,
                    'department' => $f->department,
                    'course' => $f->course,
                    'position' => $f->position,
                    'subject_name' => $enrollment->subject->name,
                    'subject_code' => $subjectCode,
                    'section_name' => 'Irregular',
                    'is_evaluated' => in_array($f->id, $evaluatedFacultyIds),
                ];
            }
        } else {
            // Fetch assignments for the student's section
            $query = FacultyAssignment::with(['faculty.user', 'subject', 'section'])
                ->where('section_id', $sectionId);

            if ($activeSemester) {
                $query->where('semester', $activeSemester);
            }
            if ($activeAcademicYear) {
                $query->where('academic_year', $activeAcademicYear);
            }

            $assignments = $query->get();

            foreach ($assignments as $assignment) {
                $f = $assignment->faculty;
                if (!$f) continue;

                $subjectCode = $assignment->subject->code ?? $assignment->subject->name;

                if (isset($facultyDataMap[$f->id])) {
                    $facultyDataMap[$f->id]['subject_name'] .= ', ' . $assignment->subject->name;
                    $facultyDataMap[$f->id]['subject_code'] .= ', ' . $subjectCode;
                    continue;
                }

                $facultyDataMap[$f->id] = [
                    'id' => $f->id,
                    'evaluatee_type' => 'faculty',
                    'type' => 'faculty',
                    'assignment_id' => $assignment->id,
                    'user' => $f->user,
                    'department' => $f->department,
                    'course' => $f->course,
                    'position' => $f->position,
                    'subject_name' => $assignment->subject->name,
                    'subject_code' => $subjectCode,
                    'section_name' => $assignment->section->name,
                    'is_evaluated' => in_array($f->id, $evaluatedFacultyIds),
                ];
            }

            // Fetch General Education faculty
            if ($studentCourseName) {
                $genEdFaculty = Faculty::with('user')
                    ->where('user_id', '!=', null)
                    ->where('department', 'General Education')
                    ->where(function($q) use ($studentCourseName) {
                        $q->where('course', 'like', '%All Course%')
                          ->orWhere('course', 'like', "%{$studentCourseName}%");
                    })->get();

                foreach ($genEdFaculty as $f) {
                    if (isset($facultyDataMap[$f->id])) continue;
                    if (!$f->user || !$f->user->is_active) continue;

                    $facultyDataMap[$f->id] = [
                        'id' => $f->id,
                        'evaluatee_type' => 'faculty',
                        'type' => 'faculty',
                        'assignment_id' => 'gened-' . $f->id,
                        'user' => $f->user,
                        'department' => $f->department,
                        'course' => $f->course,
                        'position' => $f->position,
                        'subject_name' => 'General Education Subject',
                        'subject_code' => 'GEN-ED',
                        'section_name' => $studentSection->name ?? 'N/A',
                        'is_evaluated' => in_array($f->id, $evaluatedFacultyIds),
                    ];
                }
            }
        }

        return response()->json(array_values($facultyDataMap));
    }

    public function getFacultyToEvaluate(Request $request)
    {
        // Deprecated: Use getEvaluatees with evaluatee_type=faculty instead
        return $this->getEvaluatees($request->merge(['evaluatee_type' => 'faculty']));
    }

    public function store(Request $request)
    {
        $evaluateeType = $request->input('evaluatee_type', 'faculty');

        $request->validate([
            'evaluatee_type' => ['required', new ValidEvaluateeType()],
            'evaluatee_id' => ['required', 'uuid', new EvaluateeExists($evaluateeType)],
            'semester' => 'required',
            'academic_year' => 'required',
            'subject_code' => 'nullable|string',
            'year_section' => 'nullable|string',
            'comments' => 'nullable|string',
            'answers' => 'required|array',
            'answers.*.question_id' => 'required|exists:evaluation_questions,id',
            'answers.*.rating' => 'required|integer|min:1|max:5',
            'ai_analysis' => 'nullable|array',
        ]);

        $status = \App\Models\Setting::where('key', 'evaluation_status')->value('value');
        if ($status !== 'open') {
            return response()->json(['message' => 'Evaluations are currently closed.'], 403);
        }

        $studentId = $request->user()->id;

        // Check for duplicate submission
        $exists = Evaluation::where([
            'student_id' => $studentId,
            'evaluatee_id' => $request->evaluatee_id,
            'evaluatee_type' => $evaluateeType,
            'semester' => $request->semester,
            'academic_year' => $request->academic_year
        ])->exists();

        if ($exists) {
            return response()->json(['message' => 'You have already evaluated this evaluatee for this semester.'], 422);
        }

        // Validate assignment for faculty (staff has no restrictions)
        if ($evaluateeType === EvaluateeType::FACULTY->value) {
            if (!$this->validateFacultyAssignment($request->user(), $request->evaluatee_id, $request->semester, $request->academic_year)) {
                return response()->json(['message' => 'This faculty is not assigned to your section or enrolled subjects.'], 403);
            }
        }

        // Check AI moderation if present
        $aiAnalysis = $request->ai_analysis;
        if ($aiAnalysis && isset($aiAnalysis['moderation_status']) && $aiAnalysis['moderation_status'] === 'inappropriate') {
            return response()->json([
                'message' => 'Your comment was flagged as inappropriate: ' . ($aiAnalysis['moderation_reason'] ?? 'Please maintain professional language.'),
                'ai_flagged' => true
            ], 422);
        }

        DB::beginTransaction();
        try {
            $evaluation = Evaluation::create([
                'student_id' => $studentId,
                'evaluatee_id' => $request->evaluatee_id,
                'evaluatee_type' => $evaluateeType,
                'semester' => $request->semester,
                'academic_year' => $request->academic_year,
                'subject_code' => $request->subject_code,
                'year_section' => $request->year_section,
                'comments' => $request->comments,
                'ai_analysis' => $aiAnalysis,
                // Keep faculty_id for backward compatibility
                'faculty_id' => $evaluateeType === 'faculty' ? $request->evaluatee_id : null,
            ]);

            foreach ($request->answers as $answer) {
                Answer::create([
                    'evaluation_id' => $evaluation->id,
                    'question_id' => $answer['question_id'],
                    'rating' => $answer['rating']
                ]);
            }

            DB::commit();
            return response()->json(['message' => 'Evaluation submitted successfully']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Evaluation store error: ' . $e->getMessage());
            return response()->json(['message' => 'System error'], 500);
        }
    }

    private function validateFacultyAssignment($user, $facultyId, $semester, $academicYear)
    {
        $user->load('student.section_relationship.course');
        $studentType = $user->student ? $user->student->student_type : 'regular';
        $studentSectionId = $user->student ? $user->student->section_id : null;
        $studentCourseName = ($user->student && $user->student->section_relationship) ? ($user->student->section_relationship->course->name ?? null) : null;

        if ($studentType === 'irregular') {
            return \App\Models\Enrollment::where([
                'student_id' => $user->id,
                'instructor_id' => $facultyId,
                'semester' => $semester,
                'academic_year' => $academicYear
            ])->exists();
        }

        $assignmentExists = FacultyAssignment::where([
            'faculty_id' => $facultyId,
            'section_id' => $studentSectionId,
            'semester' => $semester,
            'academic_year' => $academicYear
        ])->exists();

        if ($assignmentExists) {
            return true;
        }

        // Check if General Education faculty
        $genEdFaculty = Faculty::find($facultyId);
        if ($genEdFaculty && $genEdFaculty->department === 'General Education') {
            if (str_contains($genEdFaculty->course, 'All Course') || ($studentCourseName && str_contains($genEdFaculty->course, $studentCourseName))) {
                return true;
            }
        }

        return false;
    }

    public function getResults(Request $request, $evaluateeId = null)
    {
        try {
            // Support both old and new parameter styles
            $evaluateeId = $evaluateeId ?? $request->input('evaluatee_id');
            $evaluateeType = $request->input('evaluatee_type', 'faculty');
            $departmentFilter = $request->query('department');

            $settings = \App\Models\Setting::all()->pluck('value', 'key');
            $activeSemester = $settings->get('active_semester');
            $activeAcademicYear = $settings->get('active_academic_year');

            $query = DB::table('evaluation_answers')
                ->join('evaluations', 'evaluation_answers.evaluation_id', '=', 'evaluations.id')
                ->join('evaluation_questions', 'evaluation_answers.question_id', '=', 'evaluation_questions.id')
                ->join('evaluation_categories', 'evaluation_questions.category_id', '=', 'evaluation_categories.id');

            if ($evaluateeType === 'faculty') {
                $query->where(function ($q) {
                    $q->where('evaluations.evaluatee_type', 'faculty')
                        ->orWhereNotNull('evaluations.faculty_id');
                });
            } else {
                $query->where('evaluations.evaluatee_type', $evaluateeType);
                $query->join('staff', 'evaluations.evaluatee_id', '=', 'staff.id');
            }

            if ($evaluateeId !== 'all') {
                if ($evaluateeType === 'faculty') {
                    $query->where(function ($q) use ($evaluateeId) {
                        $q->where('evaluations.faculty_id', $evaluateeId)
                            ->orWhere(function ($q2) use ($evaluateeId) {
                                $q2->where('evaluations.evaluatee_type', 'faculty')
                                    ->where('evaluations.evaluatee_id', $evaluateeId);
                            });
                    });
                } else {
                    $query->where('evaluations.evaluatee_id', $evaluateeId);
                }
            } elseif ($departmentFilter && $departmentFilter !== 'all') {
                if ($evaluateeType === 'faculty') {
                    $query->join('faculty', DB::raw('COALESCE(evaluations.evaluatee_id, evaluations.faculty_id)'), '=', 'faculty.id');
                    $query->where('faculty.department', $departmentFilter);
                } elseif ($evaluateeType === 'staff') {
                    $query->where('staff.department', $departmentFilter);
                }
            }

            if ($activeSemester) {
                $query->where('evaluations.semester', $activeSemester);
            }
            if ($activeAcademicYear) {
                $query->where('evaluations.academic_year', $activeAcademicYear);
            }

            // Filter by evaluatee_type in categories to get correct questionnaire
            $query->where('evaluation_categories.evaluatee_type', $evaluateeType);

            $results = $query->select(
                    'evaluation_categories.category_name',
                    'evaluation_categories.weight',
                    DB::raw('AVG(evaluation_answers.rating) as average_rating'),
                    DB::raw('COUNT(CASE WHEN evaluation_answers.rating = 5 THEN 1 END) as count_5'),
                    DB::raw('COUNT(CASE WHEN evaluation_answers.rating = 4 THEN 1 END) as count_4'),
                    DB::raw('COUNT(CASE WHEN evaluation_answers.rating = 3 THEN 1 END) as count_3'),
                    DB::raw('COUNT(CASE WHEN evaluation_answers.rating = 2 THEN 1 END) as count_2'),
                    DB::raw('COUNT(CASE WHEN evaluation_answers.rating = 1 THEN 1 END) as count_1')
                )
                ->groupBy('evaluation_categories.id', 'evaluation_categories.category_name', 'evaluation_categories.weight')
                ->get();

            if ($results->isEmpty()) {
                $repairedCount = app(EvaluationAnswerRepairService::class)->repairOrphans(
                    $evaluateeType,
                    $activeSemester,
                    $activeAcademicYear
                );

                if ($repairedCount > 0) {
                    $results = (clone $query)->select(
                        'evaluation_categories.category_name',
                        'evaluation_categories.weight',
                        DB::raw('AVG(evaluation_answers.rating) as average_rating'),
                        DB::raw('COUNT(CASE WHEN evaluation_answers.rating = 5 THEN 1 END) as count_5'),
                        DB::raw('COUNT(CASE WHEN evaluation_answers.rating = 4 THEN 1 END) as count_4'),
                        DB::raw('COUNT(CASE WHEN evaluation_answers.rating = 3 THEN 1 END) as count_3'),
                        DB::raw('COUNT(CASE WHEN evaluation_answers.rating = 2 THEN 1 END) as count_2'),
                        DB::raw('COUNT(CASE WHEN evaluation_answers.rating = 1 THEN 1 END) as count_1')
                    )
                        ->groupBy('evaluation_categories.id', 'evaluation_categories.category_name', 'evaluation_categories.weight')
                        ->get();
                }
            }

            if ($results->isEmpty()) {
                $fallback = DB::table('evaluation_answers')
                    ->join('evaluations', 'evaluation_answers.evaluation_id', '=', 'evaluations.id');

                if ($evaluateeType === 'faculty') {
                    $fallback->where(function ($q) {
                        $q->where('evaluations.evaluatee_type', 'faculty')
                            ->orWhereNotNull('evaluations.faculty_id');
                    });
                } else {
                    $fallback->where('evaluations.evaluatee_type', $evaluateeType);
                    $fallback->join('staff', 'evaluations.evaluatee_id', '=', 'staff.id');
                }

                if ($evaluateeId !== 'all') {
                    if ($evaluateeType === 'faculty') {
                        $fallback->where(function ($q) use ($evaluateeId) {
                            $q->where('evaluations.faculty_id', $evaluateeId)
                                ->orWhere(function ($q2) use ($evaluateeId) {
                                    $q2->where('evaluations.evaluatee_type', 'faculty')
                                        ->where('evaluations.evaluatee_id', $evaluateeId);
                                });
                        });
                    } else {
                        $fallback->where('evaluations.evaluatee_id', $evaluateeId);
                    }
                } elseif ($departmentFilter && $departmentFilter !== 'all') {
                    if ($evaluateeType === 'faculty') {
                        $fallback->join('faculty', DB::raw('COALESCE(evaluations.evaluatee_id, evaluations.faculty_id)'), '=', 'faculty.id');
                        $fallback->where('faculty.department', $departmentFilter);
                    } elseif ($evaluateeType === 'staff') {
                        $fallback->where('staff.department', $departmentFilter);
                    }
                }

                if ($activeSemester) {
                    $fallback->where('evaluations.semester', $activeSemester);
                }
                if ($activeAcademicYear) {
                    $fallback->where('evaluations.academic_year', $activeAcademicYear);
                }

                $fallbackRow = $fallback->select(
                    DB::raw("'Overall Performance' as category_name"),
                    DB::raw('1 as weight'),
                    DB::raw('AVG(evaluation_answers.rating) as average_rating'),
                    DB::raw('COUNT(CASE WHEN evaluation_answers.rating = 5 THEN 1 END) as count_5'),
                    DB::raw('COUNT(CASE WHEN evaluation_answers.rating = 4 THEN 1 END) as count_4'),
                    DB::raw('COUNT(CASE WHEN evaluation_answers.rating = 3 THEN 1 END) as count_3'),
                    DB::raw('COUNT(CASE WHEN evaluation_answers.rating = 2 THEN 1 END) as count_2'),
                    DB::raw('COUNT(CASE WHEN evaluation_answers.rating = 1 THEN 1 END) as count_1')
                )->first();

                if ($fallbackRow && $fallbackRow->average_rating !== null) {
                    $results = collect([$fallbackRow]);
                }
            }

            $totalScore = 0;
            foreach ($results as $result) {
                $totalScore += $result->average_rating * $result->weight;
            }

            return response()->json([
                'category_results' => $results,
                'final_score' => round($totalScore, 2),
                'interpretation' => $this->interpretScore($totalScore),
                'evaluatee_type' => $evaluateeType
            ]);
        } catch (\Exception $e) {
            Log::error('Get results error: ' . $e->getMessage());
            return response()->json(['message' => 'Error computing scores'], 500);
        }
    }

    private function interpretScore($score)
    {
        if ($score >= 4.50) return 'Excellent';
        if ($score >= 3.50) return 'Very Good';
        if ($score >= 2.50) return 'Good';
        if ($score >= 1.50) return 'Fair';
        return 'Poor';
    }
}
