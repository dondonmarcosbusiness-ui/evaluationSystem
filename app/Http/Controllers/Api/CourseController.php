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
            $perPage = min(max((int) $request->query('per_page', 4), 1), 100);
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
            'subjects_meta' => 'nullable|array',
            'subjects_meta.*.text' => 'required_with:subjects_meta|string|max:255',
            'subjects_meta.*.year_level' => \App\Support\YearLevel::rule(false),
            'sections_meta' => 'nullable|array',
            'sections_meta.*.text' => 'required_with:sections_meta|string|max:255',
            'sections_meta.*.year_level' => \App\Support\YearLevel::rule(false),
        ]);

        DB::beginTransaction();
        try {
            $course = Course::create($validated);

            // Sync subjects (meta entries carry per-item year levels; legacy string has none)
            foreach ($this->subjectEntries($request) as $entry) {
                ['code' => $code, 'name' => $name] = $this->parseSubjectString($entry['text']);
                Subject::create([
                    'course_id' => $course->id,
                    'name' => $name,
                    'code' => $code,
                    'year_level' => $entry['year_level'],
                ]);
            }

            // Sync sections
            foreach ($this->sectionEntries($request) as $entry) {
                Section::create([
                    'name' => $entry['text'],
                    'course_id' => $course->id,
                    'year_level' => $entry['year_level'],
                ]);
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
            'subjects_meta' => 'nullable|array',
            'subjects_meta.*.text' => 'required_with:subjects_meta|string|max:255',
            'subjects_meta.*.year_level' => \App\Support\YearLevel::rule(false),
            'sections_meta' => 'nullable|array',
            'sections_meta.*.text' => 'required_with:sections_meta|string|max:255',
            'sections_meta.*.year_level' => \App\Support\YearLevel::rule(false),
        ]);

        DB::beginTransaction();
        try {
            $course->update($validated);

            // Sync subjects only when subject data was sent (name-only edits keep existing subjects).
            if ($request->exists('subjects') || $request->exists('subjects_meta')) {
                $processedSubjectIds = [];
                foreach ($this->subjectEntries($request) as $entry) {
                    ['code' => $code, 'name' => $name] = $this->parseSubjectString($entry['text']);
                    $subject = Subject::updateOrCreate(
                        ['course_id' => $course->id, 'name' => $name],
                        ['code' => $code, 'year_level' => $entry['year_level']]
                    );
                    $processedSubjectIds[] = $subject->id;
                }
                Subject::where('course_id', $course->id)->whereNotIn('id', $processedSubjectIds)->delete();
            }

            // Sync sections only when section data was sent.
            if ($request->exists('sections') || $request->exists('sections_meta')) {
                $processedSectionIds = [];
                foreach ($this->sectionEntries($request) as $entry) {
                    $section = Section::firstOrCreate(
                        ['name' => $entry['text'], 'course_id' => $course->id],
                        ['year_level' => $entry['year_level']]
                    );
                    if (!$section->wasRecentlyCreated && $entry['year_level'] !== null
                        && $section->year_level !== $entry['year_level']) {
                        $section->update(['year_level' => $entry['year_level']]);
                    }
                    $processedSectionIds[] = $section->id;
                }
                Section::where('course_id', $course->id)->whereNotIn('id', $processedSectionIds)->delete();
            }

            DB::commit();
            return response()->json($course->load(['academic_subjects', 'academic_sections']));
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error updating course'], 500);
        }
    }

    /**
     * Normalize subject input: meta entries win; legacy comma string has no years.
     */
    protected function subjectEntries(Request $request): array
    {
        if (is_array($request->input('subjects_meta'))) {
            return array_values(array_filter(array_map(function ($item) {
                $text = trim($item['text'] ?? '');
                if ($text === '') {
                    return null;
                }
                return ['text' => $text, 'year_level' => $item['year_level'] ?? null];
            }, $request->input('subjects_meta'))));
        }

        return array_map(
            fn ($s) => ['text' => $s, 'year_level' => null],
            array_filter(array_map('trim', explode(',', $request->input('subjects', ''))))
        );
    }

    protected function sectionEntries(Request $request): array
    {
        if (is_array($request->input('sections_meta'))) {
            return array_values(array_filter(array_map(function ($item) {
                $text = trim($item['text'] ?? '');
                if ($text === '') {
                    return null;
                }
                return ['text' => $text, 'year_level' => $item['year_level'] ?? null];
            }, $request->input('sections_meta'))));
        }

        return array_map(
            fn ($s) => ['text' => $s, 'year_level' => null],
            array_filter(array_map('trim', explode(',', $request->input('sections', ''))))
        );
    }

    /**
     * Split "IT101 - Introduction..." into code + name.
     */
    protected function parseSubjectString(string $s): array
    {
        if (preg_match('/^([^\-\x{2013}]+)\s*[-\x{2013}]\s*(.+)$/u', $s, $matches)) {
            return ['code' => trim($matches[1]), 'name' => trim($matches[2])];
        }

        if (strpos($s, ' ') !== false) {
            $parts = explode(' ', $s, 2);
            return ['code' => trim($parts[0]), 'name' => trim($parts[1])];
        }

        return ['code' => $s, 'name' => $s];
    }

    /**
     * Add a single subject to a course, tagged with a year level.
     */
    public function storeSubject(Request $request, Course $course)
    {
        $validated = $request->validate([
            'text' => 'required|string|max:255',
            'year_level' => \App\Support\YearLevel::rule(false),
        ]);

        ['code' => $code, 'name' => $name] = $this->parseSubjectString($validated['text']);

        // A null year (added from the All tab) never clears an existing tag.
        $subjectAttrs = ['code' => $code];
        if ($validated['year_level'] !== null) {
            $subjectAttrs['year_level'] = $validated['year_level'];
        }
        // New records default to null (adopted by all years).
        $subject = Subject::updateOrCreate(
            ['course_id' => $course->id, 'name' => $name],
            $subjectAttrs
        );

        return response()->json($subject, 201);
    }

    /**
     * Batch upload subjects to a course via CSV.
     * Headers: "code" (optional), "subject" (required) plus optional
     * "year level". Rows without a year inherit the request's
     * year_level (the modal's active tab; null when "All").
     */
    public function importSubjects(Request $request, Course $course)
    {
        $request->validate([
            'file' => 'required|mimes:csv,txt|max:2048',
            'year_level' => \App\Support\YearLevel::rule(false),
        ]);

        $fallbackYear = $request->input('year_level');
        // Strip a UTF-8 BOM (our own CSV template — and Excel — include one,
        // otherwise the first header never matches and its column is dropped).
        $csvData = preg_replace('/^\xEF\xBB\xBF/', '', file_get_contents($request->file('file')));
        $rows = array_map('str_getcsv', explode("\n", $csvData));
        $header = array_shift($rows);
        $header = array_map(fn ($val) => strtolower(trim($val ?? '')), $header ?? []);

        if (!in_array('subject', $header, true)) {
            return response()->json([
                'message' => 'Invalid CSV format. Expected columns: Code, Subject, Year Level (optional)',
            ], 400);
        }

        $hasCodeColumn = in_array('code', $header, true);
        $hasYearColumn = in_array('year level', $header, true);
        $imported = 0;
        $failed = 0;

        DB::beginTransaction();
        try {
            foreach ($rows as $row) {
                if (count($row) !== count($header)) {
                    continue;
                }

                $data = array_combine($header, $row);
                $name = trim($data['subject'] ?? '');
                if ($name === '') {
                    $failed++;
                    continue;
                }
                $code = $hasCodeColumn ? trim($data['code'] ?? '') : '';
                $code = $code !== '' ? $code : null;

                $csvYear = $hasYearColumn ? trim($data['year level'] ?? '') : '';
                if ($csvYear !== '' && !in_array($csvYear, \App\Support\YearLevel::VALUES, true)) {
                    $failed++;
                    continue;
                }
                $year = $csvYear !== '' ? $csvYear : $fallbackYear;

                // Same semantics as single-add: a null year never clears an
                // existing tag; re-uploads update rather than duplicate.
                $subjectAttrs = ['code' => $code];
                if ($year !== null) {
                    $subjectAttrs['year_level'] = $year;
                }
                Subject::updateOrCreate(
                    ['course_id' => $course->id, 'name' => $name],
                    $subjectAttrs
                );
                $imported++;
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error importing subjects.'], 500);
        }

        return response()->json([
            'message' => 'Import completed',
            'imported' => $imported,
            'failed' => $failed,
        ]);
    }

    /**
     * Add a single section to a course, tagged with a year level.
     */
    public function storeSection(Request $request, Course $course)
    {
        $validated = $request->validate([
            'text' => 'required|string|max:255',
            'year_level' => \App\Support\YearLevel::rule(false),
        ]);

        $section = Section::firstOrCreate(
            ['name' => trim($validated['text']), 'course_id' => $course->id],
            ['year_level' => $validated['year_level']]
        );
        // A null year (added from the All tab) never clears an existing tag.
        if (!$section->wasRecentlyCreated && $validated['year_level'] !== null
            && $section->year_level !== $validated['year_level']) {
            $section->update(['year_level' => $validated['year_level']]);
        }

        return response()->json($section, 201);
    }

    public function destroySubject(Course $course, Subject $subject)
    {
        if ($subject->course_id !== $course->id) {
            return response()->json(['message' => 'Subject does not belong to this course.'], 422);
        }
        $subject->delete();

        return response()->json(['message' => 'Subject removed successfully.']);
    }

    /**
     * Delete multiple subjects of a course. Ids scoped to the course, so
     * foreign ids are silently ignored rather than rejected.
     */
    public function bulkDestroySubjects(Request $request, Course $course)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'string',
        ]);

        $deleted = Subject::where('course_id', $course->id)
            ->whereIn('id', $validated['ids'])
            ->delete();

        return response()->json(['message' => 'Subjects deleted.', 'deleted' => $deleted]);
    }

    public function destroySection(Course $course, Section $section)
    {
        if ($section->course_id !== $course->id) {
            return response()->json(['message' => 'Section does not belong to this course.'], 422);
        }
        $section->delete();

        return response()->json(['message' => 'Section removed successfully.']);
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
