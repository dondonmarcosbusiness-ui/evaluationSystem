<?php

namespace App\Services;

use App\Models\Evaluation;
use App\Models\Faculty;
use App\Models\FacultyAssignment;
use App\Models\User;
use App\Support\YearLevel;

/**
 * Evaluatee list for a student in a given period, plus the completion flag.
 *
 * Single source of truth for "which evaluatees does this student still owe".
 * Consumed by the student-facing evaluatees endpoint, the submission-time
 * completion check (which drives the confirmation email and the dashboard's
 * "students finished evaluating" metric) and the backfill migration.
 */
class EvaluateeService
{
    /**
     * Evaluatees assigned to the student for the period, each with an
     * is_evaluated flag.
     *
     * @return array<int, array<string, mixed>>
     */
    public function build(User $user, ?string $activeSemester, ?string $activeAcademicYear): array
    {
        $user->load('student.section_relationship.course');
        $studentSection = $user->student ? $user->student->section_relationship : null;
        $studentCourseName = $studentSection ? ($studentSection->course->name ?? null) : null;
        $sectionId = $user->student ? $user->student->section_id : null;
        $studentType = $user->student ? $user->student->student_type : 'regular';

        // Imported legacy records may have the section name but no section_id.
        if (!$sectionId && $user->student?->section && $user->student?->course) {
            $sectionId = \App\Models\Section::where('name', $user->student->section)
                ->whereHas('course', fn ($query) => $query->where('name', $user->student->course))
                ->value('id');
            $studentSection = $sectionId ? \App\Models\Section::with('course')->find($sectionId) : null;
            $studentCourseName = $studentSection?->course?->name;
        }

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
                ->when($activeSemester, fn ($q) => $q->where('semester', $activeSemester))
                ->when($activeAcademicYear, fn ($q) => $q->where('academic_year', $activeAcademicYear))
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

            // Year scoping: same-named sections across years must not collide.
            // Untagged (null) or Irregular years on either side act as wildcards.
            $studentYear = $user->student ? $user->student->year_level : null;
            $assignments = $assignments->filter(function ($assignment) use ($studentYear) {
                $assignmentYear = $assignment->year_level
                    ?? $assignment->subject->year_level
                    ?? $assignment->section->year_level;
                return YearLevel::matches($assignmentYear, $studentYear);
            });

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
                    ->where(function ($q) use ($studentCourseName) {
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

        return array_values($facultyDataMap);
    }

    /**
     * Persist the "finished evaluating" flag for the period when the student
     * has evaluated every evaluatee. Returns false (and leaves the flag alone)
     * while any evaluatee remains or when the list is empty, so a student with
     * nothing assigned is never reported as finished.
     *
     * The flag is period-scoped: it only counts for a dashboard period when
     * both period columns match, so a new semester starts everyone at zero.
     */
    public function markCompleted(User $student, string $semester, string $academicYear): bool
    {
        $evaluatees = $this->build($student, $semester, $academicYear);

        if ($evaluatees === []) {
            return false;
        }

        foreach ($evaluatees as $evaluatee) {
            if (empty($evaluatee['is_evaluated'])) {
                return false;
            }
        }

        $student->evaluations_completed_at = now();
        $student->evaluations_completed_semester = $semester;
        $student->evaluations_completed_academic_year = $academicYear;
        $student->save();

        return true;
    }
}
