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
                ->orderBy('users.name')
                ->paginate(20);

            return response()->json($assignments);
        } catch (\Exception $e) {
            Log::error('Assignment index error: ' . $e->getMessage());
            return response()->json(['message' => 'Error fetching assignments'], 500);
        }
    }

    public function store(Request $request)
    {
        $request->validate([
            'faculty_id' => 'required|exists:faculty,id',
            'subject_id' => 'required|exists:subjects,id',
            'section_id' => 'required|exists:sections,id',
            'academic_year' => 'nullable|string',
            'semester' => 'nullable|string',
        ]);

        try {
            $assignment = FacultyAssignment::create($request->all());
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
            'faculty' => Faculty::with('user')->get(),
            'subjects' => Subject::select('id', 'name', 'code', 'course_id')->get(),
            'sections' => Section::select('id', 'name', 'course_id')->get(),
            'courses' => Course::select('id', 'name', 'department')->get(),
        ]);
    }
}
