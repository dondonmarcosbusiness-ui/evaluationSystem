<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Subject;
use App\Models\Section;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CourseController extends Controller
{
    public function index(Request $request)
    {
        $query = Course::with(['academic_subjects', 'academic_sections']);

        if ($request->has('query') && $request->query('query') !== '') {
            $search = $request->query('query');
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('department', 'like', "%{$search}%");
            });
        }

        if ($request->has('paginate')) {
            $perPage = $request->query('per_page', 4);
            return response()->json($query->paginate($perPage));
        }

        return response()->json($query->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'department' => 'required|string|max:255',
            'subjects' => 'nullable|string',
            'sections' => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            $course = Course::create($validated);
            
            // Sync subjects
            $newSubjectStrings = array_filter(array_map('trim', explode(',', $request->subjects ?? '')));
            foreach ($newSubjectStrings as $s) {
                $code = null;
                $name = $s;
                
                if (preg_match('/^([^\-\x{2013}]+)\s*[-\x{2013}]\s*(.+)$/u', $s, $matches)) {
                    $code = trim($matches[1]);
                    $name = trim($matches[2]);
                } elseif (strpos($s, ' ') !== false) {
                    $parts = explode(' ', $s, 2);
                    $code = trim($parts[0]);
                    $name = trim($parts[1]);
                } else {
                    $code = $s;
                    $name = $s;
                }
                
                Subject::create([
                    'course_id' => $course->id,
                    'name' => $name,
                    'code' => $code
                ]);
            }

            // Sync sections
            $newSectionStrings = array_filter(array_map('trim', explode(',', $request->sections ?? '')));
            foreach ($newSectionStrings as $s) {
                Section::create(['name' => $s, 'course_id' => $course->id]);
            }

            DB::commit();
            return response()->json($course->load(['academic_subjects', 'academic_sections']), 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error saving course'], 500);
        }
    }

    public function update(Request $request, Course $course)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'department' => 'required|string|max:255',
            'subjects' => 'nullable|string',
            'sections' => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            $course->update($validated);

            // Sync subjects
            $newSubjectStrings = array_filter(array_map('trim', explode(',', $request->subjects ?? '')));
            $processedSubjectIds = [];

            foreach ($newSubjectStrings as $s) {
                $code = null;
                $name = $s;
                
                // Look for a code prefix like "IT101 - Introduction..." using hyphens or en-dashes
                if (preg_match('/^([^\-\x{2013}]+)\s*[-\x{2013}]\s*(.+)$/u', $s, $matches)) {
                    $code = trim($matches[1]);
                    $name = trim($matches[2]);
                } elseif (strpos($s, ' ') !== false) {
                    $parts = explode(' ', $s, 2);
                    $code = trim($parts[0]);
                    $name = trim($parts[1]);
                } else {
                    $code = $s;
                    $name = $s;
                }
                
                $subject = Subject::updateOrCreate(
                    ['course_id' => $course->id, 'name' => $name],
                    ['code' => $code]
                );
                $processedSubjectIds[] = $subject->id;
            }
            Subject::where('course_id', $course->id)->whereNotIn('id', $processedSubjectIds)->delete();

            // Sync sections
            $newSectionStrings = array_filter(array_map('trim', explode(',', $request->sections ?? '')));
            $processedSectionIds = [];

            foreach ($newSectionStrings as $s) {
                $section = Section::firstOrCreate(['name' => $s, 'course_id' => $course->id]);
                $processedSectionIds[] = $section->id;
            }
            Section::where('course_id', $course->id)->whereNotIn('id', $processedSectionIds)->delete();

            DB::commit();
            return response()->json($course->load(['academic_subjects', 'academic_sections']));
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error updating course'], 500);
        }
    }

    public function destroy(Course $course)
    {
        $courseName = $course->name;
        
        DB::beginTransaction();
        try {
            // Find all students in this course and set their user account to inactive
            $userIds = \App\Models\Student::where('course', $courseName)->pluck('user_id');
            if ($userIds->isNotEmpty()) {
                \App\Models\User::whereIn('id', $userIds)->update(['is_active' => false]);
            }
            
            $course->delete();
            
            DB::commit();
            return response()->json(['message' => 'Course deleted successfully and associated students deactivated.']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error deleting course'], 500);
        }
    }
}
