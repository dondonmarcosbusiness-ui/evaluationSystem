<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FacultyAssignment;
use App\Models\Subject;
use App\Models\Section;
use App\Models\Faculty;
use App\Models\Course;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AssignmentController extends Controller
{
    public function index(Request $request)
    {
        try {
            $assignments = FacultyAssignment::with(['faculty.user', 'subject.course', 'section'])
                ->join('faculty', 'faculty_assignments.faculty_id', '=', 'faculty.id')
                ->join('users', 'faculty.user_id', '=', 'users.id')
                ->select('faculty_assignments.*')
                ->when($request->query('query'), function ($q, $search) {
                    $q->where('users.name', 'like', "%{$search}%");
                })
                ->when($request->query('department'), function ($q, $dept) {
                    $q->where('faculty.department', $dept);
                })
                ->when($request->query('academic_year'), function ($q, $ay) {
                    $q->where('faculty_assignments.academic_year', $ay);
                })
                ->when($request->query('semester'), function ($q, $sem) {
                    $q->where('faculty_assignments.semester', $sem);
                })
                ->when($request->query('subject_id'), function ($q, $subjectId) {
                    $q->where('faculty_assignments.subject_id', $subjectId);
                })
                ->when($request->query('year_level'), function ($q, $year) {
                    $q->where(function ($qq) use ($year) {
                        $qq->where('faculty_assignments.year_level', $year)
                            ->orWhereNull('faculty_assignments.year_level');
                    });
                })
                ->orderBy('users.name')
                ->paginate(min(max((int) $request->input('per_page', 20), 1), 100));

            return response()->json($assignments);
        } catch (\Exception $e) {
            Log::error('Assignment index error: ' . $e->getMessage());
            return response()->json(['message' => 'Error fetching assignments'], 500);
        }
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'faculty_id' => 'required|exists:faculty,id',
            'subject_id' => 'required|exists:subjects,id',
            'section_id' => 'required|exists:sections,id',
            'academic_year' => 'nullable|string',
            'semester' => 'nullable|string',
            'year_level' => \App\Support\YearLevel::rule(false),
        ]);

        // Default to the subject's year when the admin leaves it blank.
        if (empty($validated['year_level'])) {
            $validated['year_level'] = Subject::whereKey($validated['subject_id'])->value('year_level');
        }

        try {
            $assignment = FacultyAssignment::create($validated);
            return response()->json([
                'message' => 'Assignment created successfully',
                'data' => $assignment->load(['faculty.user', 'subject', 'section'])
            ]);
        } catch (\Exception $e) {
            Log::error('Assignment store error: ' . $e->getMessage());
            return response()->json(['message' => 'Error creating assignment'], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $assignment = FacultyAssignment::findOrFail($id);
            $assignment->delete();
            return response()->json(['message' => 'Assignment deleted successfully']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error deleting assignment'], 500);
        }
    }

    public function getMeta()
    {
        return response()->json([
            'faculty' => Faculty::with('user:id,id_number,firstname,middlename,lastname,name,email,is_active')->get(),
            'subjects' => Subject::select('id', 'name', 'code', 'course_id', 'year_level')->get(),
            'sections' => Section::select('id', 'name', 'course_id', 'year_level')->get(),
            'year_levels' => \App\Support\YearLevel::VALUES,
            'courses' => Course::select('id', 'name', 'department')->get(),
        ]);
    }
}
