<?php

namespace Tests\Feature;

use App\Models\Answer;
use App\Models\Category;
use App\Models\Course;
use App\Models\Evaluation;
use App\Models\Faculty;
use App\Models\FacultyAssignment;
use App\Models\Permission;
use App\Models\Question;
use App\Models\Section;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubjectRatingsTest extends TestCase
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

    /** @return array{0: User, 1: Faculty} */
    private function makeFaculty(string $email = 'faculty@test.com'): array
    {
        $user = $this->grant($this->makeUser('faculty', $email), [
            'report.view',
            'report.view.own',
            'evaluation.view',
            'evaluation.view.own',
        ]);
        $faculty = Faculty::create([
            'user_id' => $user->id,
            'department' => 'CIT',
            'course' => 'BSIT',
            'position' => 'Instructor',
        ]);

        return [$user, $faculty];
    }

    private ?Question $sharedQuestion = null;

    /** One category/question shared by every evaluation in a test. */
    private function sharedQuestion(): Question
    {
        if (!$this->sharedQuestion) {
            $category = Category::create([
                'category_name' => 'Teaching Skill',
                'weight' => 1,
                'evaluatee_type' => 'faculty',
            ]);
            $this->sharedQuestion = Question::create([
                'category_id' => $category->id,
                'question_text' => 'Rates clearly?',
            ]);
        }

        return $this->sharedQuestion;
    }

    private function makeEvaluationWithRating(Faculty $faculty, string $subjectCode, int $rating, array $period = []): Evaluation
    {
        $evaluation = Evaluation::create([
            'student_id' => $this->makeUser('student', uniqid('student-') . '@test.com')->id,
            'faculty_id' => $faculty->id,
            'evaluatee_type' => 'faculty',
            'evaluatee_id' => $faculty->id,
            'semester' => $period['semester'] ?? '1st Semester',
            'academic_year' => $period['academic_year'] ?? '2025-2026',
            'subject_code' => $subjectCode,
            'comments' => 'A comment',
        ]);
        Answer::create([
            'evaluation_id' => $evaluation->id,
            'question_id' => $this->sharedQuestion()->id,
            'rating' => $rating,
        ]);

        return $evaluation;
    }

    public function test_returns_overall_and_per_subject_averages()
    {
        [$facultyUser, $faculty] = $this->makeFaculty();
        [$otherUser, $otherFaculty] = $this->makeFaculty('other@test.com');

        $this->makeEvaluationWithRating($faculty, 'IT-SIA01', 5);
        $this->makeEvaluationWithRating($faculty, 'IT-SIA01', 4);
        $this->makeEvaluationWithRating($faculty, 'IT-PE01', 3);
        // Another faculty's ratings must not leak in.
        $this->makeEvaluationWithRating($otherFaculty, 'IT-SIA01', 1);

        $res = $this->actingAs($facultyUser, 'sanctum')
            ->getJson('/api/reports/my-subject-ratings?semester=all&academic_year=all')
            ->assertOk();

        $this->assertSame(4.0, (float) $res->json('overall.average'));
        $this->assertSame(3, $res->json('overall.evaluations'));

        $subjects = collect($res->json('subjects'))->keyBy('subject_code');
        $this->assertEqualsCanonicalizing(['IT-PE01', 'IT-SIA01'], $subjects->keys()->all());
        $this->assertSame(4.5, (float) $subjects['IT-SIA01']['average']);
        $this->assertSame(2, $subjects['IT-SIA01']['evaluations']);
        $this->assertSame(3.0, (float) $subjects['IT-PE01']['average']);
    }

    public function test_assigned_subject_without_evaluations_is_listed()
    {
        [$facultyUser, $faculty] = $this->makeFaculty();

        $course = Course::create(['name' => 'BSIT', 'department' => 'CIT']);
        $subject = Subject::create([
            'course_id' => $course->id,
            'name' => 'Mathematics',
            'code' => 'IT-MA01',
        ]);
        $section = Section::create(['course_id' => $course->id, 'name' => '4A']);
        FacultyAssignment::create([
            'faculty_id' => $faculty->id,
            'subject_id' => $subject->id,
            'section_id' => $section->id,
            'academic_year' => null,
            'semester' => null,
        ]);

        $res = $this->actingAs($facultyUser, 'sanctum')
            ->getJson('/api/reports/my-subject-ratings?semester=all&academic_year=all')
            ->assertOk();

        $found = collect($res->json('subjects'))->firstWhere('subject_code', 'IT-MA01');
        $this->assertNotNull($found);
        $this->assertSame('Mathematics', $found['subject_name']);
        $this->assertNull($found['average']);
        $this->assertSame(0, $found['evaluations']);
        $this->assertNull($res->json('overall.average'));
    }

    public function test_faculty_cannot_read_another_facultys_ratings()
    {
        [, $facultyA] = $this->makeFaculty('a@test.com');
        [$facultyBUser, $facultyB] = $this->makeFaculty('b@test.com');
        $admin = $this->grant($this->makeUser('admin', 'admin@test.com'), [
            'report.view',
            'report.view.all',
        ]);
        $student = $this->makeUser('student', 'student@test.com');

        $this->actingAs($facultyBUser, 'sanctum')
            ->getJson("/api/reports/my-subject-ratings?faculty_id={$facultyA->id}")
            ->assertStatus(403);

        $this->actingAs($student, 'sanctum')
            ->getJson("/api/reports/my-subject-ratings?faculty_id={$facultyB->id}")
            ->assertStatus(403);

        $this->actingAs($admin, 'sanctum')
            ->getJson("/api/reports/my-subject-ratings?faculty_id={$facultyB->id}")
            ->assertOk()
            ->assertJsonPath('faculty_id', $facultyB->id);
    }

    public function test_results_endpoint_filters_categories_by_subject()
    {
        [$facultyUser, $faculty] = $this->makeFaculty();

        $this->makeEvaluationWithRating($faculty, 'IT-SIA01', 5);
        $this->makeEvaluationWithRating($faculty, 'IT-PE01', 1);

        $base = '/api/evaluations/results/' . $faculty->id
            . '?evaluatee_type=faculty&semester=all&academic_year=all';

        $overall = $this->actingAs($facultyUser, 'sanctum')->getJson($base)->assertOk();
        $this->assertSame(3.0, round((float) $overall->json('category_results.0.average_rating'), 1));

        $subject = $this->actingAs($facultyUser, 'sanctum')
            ->getJson($base . '&subject_code=IT-SIA01')
            ->assertOk();
        $this->assertSame(5.0, (float) $subject->json('category_results.0.average_rating'));
        $this->assertSame(5.0, (float) $subject->json('final_score'));
    }
}
