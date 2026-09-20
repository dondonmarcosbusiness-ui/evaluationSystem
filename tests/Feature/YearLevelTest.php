<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Faculty;
use App\Models\FacultyAssignment;
use App\Models\Section;
use App\Models\Setting;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use App\Models\Permission;
use Tests\TestCase;

class YearLevelTest extends TestCase
{
    use RefreshDatabase;

    private function grant(User $user, array $permissions): User
    {
        foreach ($permissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
            $user->givePermissionTo($name);
        }

        return $user;
    }

    private function makeUser(string $role, string $email): User
    {
        return User::create([
            'firstname' => 'Test',
            'lastname' => ucfirst($role),
            'email' => $email,
            'password' => 'password',
            'role' => $role,
            'is_active' => true,
        ]);
    }

    private function makeCourse(string $name = 'BSIT'): Course
    {
        return Course::create(['name' => $name, 'department' => 'CIT']);
    }

    private function makeSubject(Course $course, ?string $year, string $code = 'IT101'): Subject
    {
        return Subject::create([
            'course_id' => $course->id,
            'name' => 'Subject ' . $code,
            'code' => $code,
            'year_level' => $year,
        ]);
    }

    private function makeSection(Course $course, ?string $year, string $name = '1-A'): Section
    {
        return Section::create([
            'course_id' => $course->id,
            'name' => $name,
            'year_level' => $year,
        ]);
    }

    private function makeFaculty(string $email = 'faculty@test.com'): Faculty
    {
        $user = $this->grant($this->makeUser('faculty', $email), ['give_evaluations']);
        $user->update(['role' => 'faculty']);

        return Faculty::create([
            'user_id' => $user->id,
            'department' => 'CIT',
            'course' => 'BSIT',
            'position' => 'Instructor',
        ]);
    }

    private function openEvaluationPeriod(): void
    {
        Setting::create(['key' => 'evaluation_status', 'value' => 'open']);
        Setting::create(['key' => 'active_semester', 'value' => '1st Semester']);
        Setting::create(['key' => 'active_academic_year', 'value' => '2024-2025']);
        Cache::flush();
    }

    public function test_assignment_store_accepts_year_level()
    {
        $admin = $this->grant($this->makeUser('admin', 'admin@test.com'), ['manage_faculty']);
        $course = $this->makeCourse();
        $subject = $this->makeSubject($course, '2nd');
        $section = $this->makeSection($course, '2nd');
        $faculty = $this->makeFaculty();

        $res = $this->actingAs($admin, 'sanctum')->postJson('/api/assignments', [
            'faculty_id' => $faculty->id,
            'subject_id' => $subject->id,
            'section_id' => $section->id,
            'academic_year' => '2024-2025',
            'semester' => '1st Semester',
            'year_level' => '2nd',
        ]);

        $res->assertStatus(200);
        $this->assertDatabaseHas('faculty_assignments', [
            'subject_id' => $subject->id,
            'year_level' => '2nd',
        ]);
    }

    public function test_google_registration_infers_year_from_section_name()
    {
        \App\Models\Role::firstOrCreate(['name' => 'Student', 'guard_name' => 'web']);
        $course = $this->makeCourse();

        $res = $this->postJson('/api/auth/google/register', [
            'firstname' => 'Jane',
            'lastname' => 'Doe',
            'course' => 'BSIT',
            'section' => '4A',
            'google_id' => 'google-4a',
            'email' => 'car10444@neustcarranglan.ph.education',
        ]);

        $res->assertStatus(200);
        $this->assertDatabaseHas('students', [
            'section' => '4A',
            'year_level' => '4th',
            'student_type' => 'regular',
        ]);
    }

    public function test_google_registration_prefers_section_tag_over_inferred_name()
    {
        \App\Models\Role::firstOrCreate(['name' => 'Student', 'guard_name' => 'web']);
        $course = $this->makeCourse();
        $section = $this->makeSection($course, '3rd', '2A');

        $res = $this->postJson('/api/auth/google/register', [
            'firstname' => 'John',
            'lastname' => 'Smith',
            'course' => 'BSIT',
            'section' => '2A',
            'section_id' => $section->id,
            'google_id' => 'google-tag',
            'email' => 'car10555@neustcarranglan.ph.education',
        ]);

        $res->assertStatus(200);
        $this->assertDatabaseHas('students', [
            'section_id' => $section->id,
            'year_level' => '3rd',
        ]);
    }

    public function test_assignment_store_rejects_invalid_year_level()
    {
        $admin = $this->grant($this->makeUser('admin', 'admin@test.com'), ['manage_faculty']);
        $course = $this->makeCourse();
        $subject = $this->makeSubject($course, '1st');
        $section = $this->makeSection($course, '1st');
        $faculty = $this->makeFaculty();

        $this->actingAs($admin, 'sanctum')->postJson('/api/assignments', [
            'faculty_id' => $faculty->id,
            'subject_id' => $subject->id,
            'section_id' => $section->id,
            'year_level' => '5th',
        ])->assertStatus(422);
    }

    public function test_assignment_store_defaults_year_from_subject()
    {
        $admin = $this->grant($this->makeUser('admin', 'admin@test.com'), ['manage_faculty']);
        $course = $this->makeCourse();
        $subject = $this->makeSubject($course, '3rd');
        $section = $this->makeSection($course, null);
        $faculty = $this->makeFaculty();

        $this->actingAs($admin, 'sanctum')->postJson('/api/assignments', [
            'faculty_id' => $faculty->id,
            'subject_id' => $subject->id,
            'section_id' => $section->id,
        ])->assertStatus(200);

        $this->assertDatabaseHas('faculty_assignments', [
            'subject_id' => $subject->id,
            'year_level' => '3rd',
        ]);
    }

    public function test_assignment_index_year_filter_includes_matching_and_untagged()
    {
        $admin = $this->grant($this->makeUser('admin', 'admin@test.com'), ['manage_faculty']);
        $course = $this->makeCourse();
        $section = $this->makeSection($course, '1st');
        $faculty = $this->makeFaculty();
        $first = $this->makeSubject($course, '1st', 'IT101');
        $second = $this->makeSubject($course, '2nd', 'IT201');
        $legacy = $this->makeSubject($course, null, 'IT301');

        foreach ([$first, $second, $legacy] as $subject) {
            FacultyAssignment::create([
                'faculty_id' => $faculty->id,
                'subject_id' => $subject->id,
                'section_id' => $section->id,
                'academic_year' => '2024-2025',
                'semester' => '1st Semester',
                'year_level' => $subject->year_level,
            ]);
        }

        $res = $this->actingAs($admin, 'sanctum')->getJson('/api/assignments?year_level=1st');
        $res->assertStatus(200);
        $codes = collect($res->json('data'))->pluck('subject.code')->all();

        $this->assertContains('IT101', $codes);
        $this->assertContains('IT301', $codes);
        $this->assertNotContains('IT201', $codes);
    }

    public function test_regular_student_only_sees_matching_year_assignments()
    {
        $this->openEvaluationPeriod();
        $course = $this->makeCourse();
        $section = $this->makeSection($course, '1st', '1-A');

        $facultyFirst = $this->makeFaculty('first@test.com');
        $facultySecond = $this->makeFaculty('second@test.com');
        $facultyLegacy = $this->makeFaculty('legacy@test.com');

        $cases = [
            [$facultyFirst, $this->makeSubject($course, '1st', 'IT101'), '1st'],
            [$facultySecond, $this->makeSubject($course, '2nd', 'IT201'), '2nd'],
            [$facultyLegacy, $this->makeSubject($course, null, 'IT301'), null],
        ];
        foreach ($cases as [$faculty, $subject, $year]) {
            FacultyAssignment::create([
                'faculty_id' => $faculty->id,
                'subject_id' => $subject->id,
                'section_id' => $section->id,
                'academic_year' => '2024-2025',
                'semester' => '1st Semester',
                'year_level' => $year,
            ]);
        }

        $studentUser = $this->grant($this->makeUser('student', 'student@test.com'), ['give_evaluations']);
        Student::create([
            'user_id' => $studentUser->id,
            'course' => 'BSIT',
            'section' => '1-A',
            'section_id' => $section->id,
            'student_type' => 'regular',
            'year_level' => '1st',
        ]);

        $res = $this->actingAs($studentUser, 'sanctum')->getJson('/api/evaluations/evaluatees');
        $res->assertStatus(200);
        $ids = collect($res->json())->pluck('id')->all();

        $this->assertContains($facultyFirst->id, $ids);
        $this->assertContains($facultyLegacy->id, $ids);
        $this->assertNotContains($facultySecond->id, $ids);
    }

    public function test_student_store_requires_year_level()
    {
        \App\Models\Role::firstOrCreate(['name' => 'Student', 'guard_name' => 'web']);
        $admin = $this->grant($this->makeUser('admin', 'admin2@test.com'), ['manage_users']);
        $course = $this->makeCourse();
        $section = $this->makeSection($course, '1st');

        $payload = [
            'firstname' => 'Jane',
            'lastname' => 'Doe',
            'id_number' => '2024-0001',
            'course' => 'BSIT',
            'section_id' => $section->id,
            'student_type' => 'regular',
        ];

        $this->actingAs($admin, 'sanctum')->postJson('/api/students', $payload)->assertStatus(422);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/students', array_merge($payload, ['year_level' => '1st']))
            ->assertStatus(200);

        $userId = User::where('id_number', '2024-0001')->value('id');
        $this->assertNotNull($userId);
        $this->assertDatabaseHas('students', ['user_id' => $userId, 'year_level' => '1st']);
    }

    public function test_enrollment_inherits_subject_year()
    {
        $admin = $this->grant($this->makeUser('admin', 'admin3@test.com'), ['manage_users']);
        $course = $this->makeCourse();
        $subject = $this->makeSubject($course, '2nd', 'IT201');
        $faculty = $this->makeFaculty();
        $studentUser = $this->makeUser('student', 'irreg@test.com');

        $res = $this->actingAs($admin, 'sanctum')->postJson("/api/students/{$studentUser->id}/enrollments", [
            'subject_id' => $subject->id,
            'instructor_id' => $faculty->id,
            'semester' => '1st Semester',
            'academic_year' => '2024-2025',
        ]);

        $res->assertStatus(200);
        $this->assertDatabaseHas('enrollments', [
            'student_id' => $studentUser->id,
            'subject_id' => $subject->id,
            'year_level' => '2nd',
        ]);
    }

    public function test_detailed_report_filters_respondents_by_year()
    {
        $this->openEvaluationPeriod();
        $admin = $this->grant($this->makeUser('admin', 'admin5@test.com'), ['manage_faculty']);
        $course = $this->makeCourse();
        $section = $this->makeSection($course, '1st', '1-A');
        $faculty = $this->makeFaculty();

        $category = \App\Models\Category::create([
            'category_name' => 'Teaching',
            'weight' => 100,
            'evaluatee_type' => 'faculty',
        ]);
        $question = \App\Models\Question::create([
            'category_id' => $category->id,
            'question_text' => 'Teaches well?',
        ]);

        foreach ([['s1@test.com', '1st'], ['s2@test.com', '2nd']] as [$email, $year]) {
            $studentUser = $this->makeUser('student', $email);
            Student::create([
                'user_id' => $studentUser->id,
                'course' => 'BSIT',
                'section' => '1-A',
                'section_id' => $section->id,
                'student_type' => 'regular',
                'year_level' => $year,
            ]);
            $evaluation = \App\Models\Evaluation::create([
                'student_id' => $studentUser->id,
                'faculty_id' => $faculty->id,
                'evaluatee_type' => 'faculty',
                'evaluatee_id' => $faculty->id,
                'semester' => '1st Semester',
                'academic_year' => '2024-2025',
                'subject_code' => 'IT101',
                'year_section' => '1-A',
            ]);
            \App\Models\Answer::create([
                'evaluation_id' => $evaluation->id,
                'question_id' => $question->id,
                'rating' => 5,
            ]);
        }

        $all = $this->actingAs($admin, 'sanctum')->getJson("/api/reports/evaluatee/{$faculty->id}");
        $all->assertStatus(200);
        $this->assertEquals(2, $all->json('total_students'));

        $filtered = $this->actingAs($admin, 'sanctum')->getJson("/api/reports/evaluatee/{$faculty->id}?year_level=1st");
        $filtered->assertStatus(200);
        $this->assertEquals(1, $filtered->json('total_students'));
    }

    public function test_detailed_report_year_filter_falls_back_to_section_year()
    {
        $this->openEvaluationPeriod();
        $admin = $this->grant($this->makeUser('admin', 'admin9@test.com'), ['manage_faculty']);
        $course = $this->makeCourse();
        $section = $this->makeSection($course, '4th', '4A');
        $faculty = $this->makeFaculty();

        $category = \App\Models\Category::create([
            'category_name' => 'Teaching',
            'weight' => 100,
            'evaluatee_type' => 'faculty',
        ]);
        $question = \App\Models\Question::create([
            'category_id' => $category->id,
            'question_text' => 'Teaches well?',
        ]);

        // Respondent tagged only via section (legacy import without year_level).
        $studentUser = $this->makeUser('student', 'legacy@test.com');
        Student::create([
            'user_id' => $studentUser->id,
            'course' => 'BSIT',
            'section' => '4A',
            'section_id' => $section->id,
            'student_type' => 'regular',
            'year_level' => null,
        ]);
        $evaluation = \App\Models\Evaluation::create([
            'student_id' => $studentUser->id,
            'faculty_id' => $faculty->id,
            'evaluatee_type' => 'faculty',
            'evaluatee_id' => $faculty->id,
            'semester' => '1st Semester',
            'academic_year' => '2024-2025',
            'subject_code' => 'IT-SW01',
            'year_section' => '4A',
        ]);
        \App\Models\Answer::create([
            'evaluation_id' => $evaluation->id,
            'question_id' => $question->id,
            'rating' => 5,
        ]);

        $all = $this->actingAs($admin, 'sanctum')->getJson("/api/reports/evaluatee/{$faculty->id}");
        $all->assertStatus(200);
        $this->assertEquals(1, $all->json('total_students'));

        $wrongYear = $this->actingAs($admin, 'sanctum')->getJson("/api/reports/evaluatee/{$faculty->id}?year_level=1st");
        $wrongYear->assertStatus(200);
        $this->assertEquals(0, $wrongYear->json('total_students'));

        $rightYear = $this->actingAs($admin, 'sanctum')->getJson("/api/reports/evaluatee/{$faculty->id}?year_level=4th");
        $rightYear->assertStatus(200);
        $this->assertEquals(1, $rightYear->json('total_students'));
    }

    public function test_detailed_report_flags_fully_untagged_respondents()
    {
        $this->openEvaluationPeriod();
        $admin = $this->grant($this->makeUser('admin', 'admin10@test.com'), ['manage_faculty']);
        $course = $this->makeCourse();
        $faculty = $this->makeFaculty();

        $category = \App\Models\Category::create([
            'category_name' => 'Teaching',
            'weight' => 100,
            'evaluatee_type' => 'faculty',
        ]);
        $question = \App\Models\Question::create([
            'category_id' => $category->id,
            'question_text' => 'Teaches well?',
        ]);

        // Respondent with no year anywhere: still matches every year filter,
        // but the response flags them so the UI can explain why.
        $studentUser = $this->makeUser('student', 'untagged@test.com');
        Student::create([
            'user_id' => $studentUser->id,
            'course' => 'BSIT',
            'section' => '4A',
            'section_id' => null,
            'student_type' => 'regular',
            'year_level' => null,
        ]);
        $evaluation = \App\Models\Evaluation::create([
            'student_id' => $studentUser->id,
            'faculty_id' => $faculty->id,
            'evaluatee_type' => 'faculty',
            'evaluatee_id' => $faculty->id,
            'semester' => '1st Semester',
            'academic_year' => '2024-2025',
            'subject_code' => 'IT-SW01',
            'year_section' => '4A',
        ]);
        \App\Models\Answer::create([
            'evaluation_id' => $evaluation->id,
            'question_id' => $question->id,
            'rating' => 5,
        ]);

        $filtered = $this->actingAs($admin, 'sanctum')->getJson("/api/reports/evaluatee/{$faculty->id}?year_level=1st");
        $filtered->assertStatus(200);
        $this->assertEquals(1, $filtered->json('total_students'));
        $this->assertEquals(1, $filtered->json('untagged_respondents'));

        $unfiltered = $this->actingAs($admin, 'sanctum')->getJson("/api/reports/evaluatee/{$faculty->id}");
        $unfiltered->assertStatus(200);
        $this->assertEquals(1, $unfiltered->json('untagged_respondents'));
    }

    public function test_course_detail_can_add_subject_and_section_to_a_year()
    {
        $admin = $this->grant($this->makeUser('admin', 'admin6@test.com'), ['manage_courses']);
        $course = $this->makeCourse();

        $this->actingAs($admin, 'sanctum')->postJson("/api/courses/{$course->id}/subjects", [
            'text' => 'IT101 - Programming',
            'year_level' => '1st',
        ])->assertStatus(201);

        $this->actingAs($admin, 'sanctum')->postJson("/api/courses/{$course->id}/sections", [
            'text' => '1-A',
            'year_level' => '1st',
        ])->assertStatus(201);

        $this->assertDatabaseHas('subjects', ['course_id' => $course->id, 'code' => 'IT101', 'year_level' => '1st']);
        $this->assertDatabaseHas('sections', ['course_id' => $course->id, 'name' => '1-A', 'year_level' => '1st']);
    }

    public function test_course_detail_add_rejects_invalid_year()
    {
        $admin = $this->grant($this->makeUser('admin', 'admin7@test.com'), ['manage_courses']);
        $course = $this->makeCourse();

        $this->actingAs($admin, 'sanctum')->postJson("/api/courses/{$course->id}/subjects", [
            'text' => 'IT101 - Programming',
            'year_level' => '5th',
        ])->assertStatus(422);

        $this->actingAs($admin, 'sanctum')->postJson("/api/courses/{$course->id}/sections", [
            'text' => '1-A',
            'year_level' => '5th',
        ])->assertStatus(422);
    }

    public function test_course_detail_add_without_year_adopts_all_years()
    {
        $admin = $this->grant($this->makeUser('admin', 'admin10@test.com'), ['manage_courses']);
        $course = $this->makeCourse();

        $this->actingAs($admin, 'sanctum')->postJson("/api/courses/{$course->id}/subjects", [
            'text' => 'IT101 - Programming',
            'year_level' => null,
        ])->assertStatus(201);

        $this->actingAs($admin, 'sanctum')->postJson("/api/courses/{$course->id}/sections", [
            'text' => '1-A',
            'year_level' => null,
        ])->assertStatus(201);

        $this->assertDatabaseHas('subjects', ['course_id' => $course->id, 'code' => 'IT101', 'year_level' => null]);
        $this->assertDatabaseHas('sections', ['course_id' => $course->id, 'name' => '1-A', 'year_level' => null]);
    }

    public function test_course_detail_can_remove_subject_and_section()
    {
        $admin = $this->grant($this->makeUser('admin', 'admin8@test.com'), ['manage_courses']);
        $course = $this->makeCourse();
        $subject = $this->makeSubject($course, '1st', 'IT101');
        $section = $this->makeSection($course, '1st', '1-A');

        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/courses/{$course->id}/subjects/{$subject->id}")
            ->assertStatus(200);
        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/courses/{$course->id}/sections/{$section->id}")
            ->assertStatus(200);

        $this->assertDatabaseMissing('subjects', ['id' => $subject->id]);
        $this->assertDatabaseMissing('sections', ['id' => $section->id]);
    }

    public function test_course_name_only_update_preserves_subjects_and_sections()
    {
        $admin = $this->grant($this->makeUser('admin', 'admin9@test.com'), ['manage_courses']);
        $course = $this->makeCourse();
        $subject = $this->makeSubject($course, '1st', 'IT101');
        $section = $this->makeSection($course, '1st', '1-A');

        $this->actingAs($admin, 'sanctum')->putJson("/api/courses/{$course->id}", [
            'name' => 'BSIT Renamed',
            'department' => 'CIT',
        ])->assertStatus(200);

        $this->assertDatabaseHas('subjects', ['id' => $subject->id, 'year_level' => '1st']);
        $this->assertDatabaseHas('sections', ['id' => $section->id, 'year_level' => '1st']);
    }

    public function test_course_update_persists_subject_and_section_years()
    {
        $admin = $this->grant($this->makeUser('admin', 'admin4@test.com'), ['manage_courses']);
        $course = $this->makeCourse();

        $res = $this->actingAs($admin, 'sanctum')->putJson("/api/courses/{$course->id}", [
            'name' => 'BSIT',
            'department' => 'CIT',
            'subjects' => 'IT101 - Programming',
            'sections' => '1-A',
            'subjects_meta' => [['text' => 'IT101 - Programming', 'year_level' => '1st']],
            'sections_meta' => [['text' => '1-A', 'year_level' => '1st']],
        ]);

        $res->assertStatus(200);
        $this->assertDatabaseHas('subjects', ['course_id' => $course->id, 'code' => 'IT101', 'year_level' => '1st']);
        $this->assertDatabaseHas('sections', ['course_id' => $course->id, 'name' => '1-A', 'year_level' => '1st']);
    }
}
