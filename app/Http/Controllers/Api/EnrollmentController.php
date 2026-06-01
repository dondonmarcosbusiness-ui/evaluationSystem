<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class EnrollmentController extends Controller
{
    public function index(Request $request, $studentId)
    {
        try {
            $enrollments = Enrollment::with(['subject', 'instructor.user'])
                ->where('student_id', $studentId)
                ->get();
            return response()->json($enrollments);
        } catch (\Exception $e) {
            Log::error('Enrollment index error: ' . $e->getMessage());
            return response()->json(['message' => 'System error'], 500);
        }
    }

    public function store(Request $request, $studentId)
    {
        $request->validate([
            'subject_id' => 'required|exists:subjects,id',
            'instructor_id' => 'required|exists:faculty,id',
            'semester' => 'required|string',
            'academic_year' => 'required|string',
        ]);

        try {
            $enrollment = Enrollment::updateOrCreate(
                [
                    'student_id' => $studentId,
                    'subject_id' => $request->subject_id,
                    'instructor_id' => $request->instructor_id,
                    'semester' => $request->semester,
                    'academic_year' => $request->academic_year,
                ]
            );

            return response()->json([
                'message' => 'Enrollment added successfully',
                'data' => $enrollment->load(['subject', 'instructor.user'])
            ]);
        } catch (\Exception $e) {
            Log::error('Enrollment store error: ' . $e->getMessage());
            return response()->json(['message' => 'System error'], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $enrollment = Enrollment::findOrFail($id);
            $enrollment->delete();
            return response()->json(['message' => 'Enrollment removed successfully']);
        } catch (\Exception $e) {
            Log::error('Enrollment destroy error: ' . $e->getMessage());
            return response()->json(['message' => 'System error'], 500);
        }
    }
}
