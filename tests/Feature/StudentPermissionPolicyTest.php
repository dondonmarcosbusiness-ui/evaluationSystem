<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Course;
use App\Models\Faculty;
use App\Models\FacultyAssignment;
use App\Models\Permission;
use App\Models\Question;
use App\Models\Role;
use App\Models\Section;
use App\Models\Setting;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Notifications\EvaluationCompletedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Business rules:
 *  - admin delegates system control to FACULTY granularly (per-button);
 *  - students may never hold permissions outside the evaluation allowlist;
 *  - a student gets one confirmation email after finishing ALL evaluations.
 */
class StudentPermissionPolicyTest extends TestCase
{
    use RefreshDatabase;

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

    private function grant(User $user, array $permissions): User
    {
        foreach ($permissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
            $user->givePermissionTo($name);
        }

        return $user;
    }

    private function makeStudentRecord(User $studentUser, Section $section): Student
    {
        return Student::create([
            'user_id' => $studentUser->id,
            'course' => $section->course->name,
            'section' => $section->name,
            'section_id' => $section->id,
            'student_type' => 'regular',
            'year_level' => '1st',
        ]);
    }

    /** @return array{0: Course, 1: Section, 2: Subject} */
    private function makeAcademicFixture(): array
    {
        $course = Course::create(['name' => 'BSIT', 'department' => 'CIT']);
        $section = Section::create(['course_id' => $course->id, 'name' => '1-A', 'year_level' => '1st']);
        $subject = Subject::create([
            'course_id' => $course->id,
            'name' => 'Subject IT101',
            'code' => 'IT101',
            'year_level' => '1st',
        ]);

        return [$course, $section, $subject];
    }

    private function makeFacultyWithUser(string $email): Faculty
    {
        $user = $this->makeUser('faculty', $email);

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

    private function evaluationPayload(Faculty $faculty): array
    {
        $category = Category::create([
            'category_name' => 'Teaching Skill ' . uniqid(),
            'weight' => 1,
            'evaluatee_type' => 'faculty',
        ]);
        $question = Question::create([
            'category_id' => $category->id,
            'question_text' => 'Rates clearly?',
        ]);

        return [
            'evaluatee_type' => 'faculty',
            'evaluatee_id' => $faculty->id,
            'semester' => '1st Semester',
            'academic_year' => '2024-2025',
            'subject_code' => 'IT101',
            'year_section' => '1-A',
            'comments' => 'Good',
            'answers' => [
                ['question_id' => $question->id, 'rating' => 5],
            ],
        ];
    }

    public function test_faculty_gets_every_student_button_except_delete_when_not_granted(): void
    {
        $facultyUser = $this->grant(
            $this->makeUser('faculty', 'delegate@test.com'),
            ['student.view', 'student.create', 'student.edit', 'student.import']
        );

        $studentUser = $this->makeUser('student', 'target@test.com');
        $course = Course::create(['name' => 'BSIT', 'department' => 'CIT']);
        $section = Section::create(['course_id' => $course->id, 'name' => '1-A', 'year_level' => '1st']);
        $this->makeStudentRecord($studentUser, $section);

        // Buttons visible = the underlying endpoints are allowed.
        $this->actingAs($facultyUser, 'sanctum')
            ->getJson('/api/students')
            ->assertStatus(200);

        // Delete was NOT granted → endpoint denies (frontend hides the button).
        $this->actingAs($facultyUser, 'sanctum')
            ->deleteJson("/api/students/{$studentUser->id}")
            ->assertStatus(403);

        $this->assertSame(
            1,
            \App\Models\AuditLog::where('action', 'permission.denied')
                ->where('user_id', $facultyUser->id)
                ->count()
        );

        $this->assertDatabaseHas('users', ['id' => $studentUser->id]);

        // Granting student.delete flips the capability on.
        $this->grant($facultyUser, ['student.delete']);

        $this->actingAs($facultyUser, 'sanctum')
            ->deleteJson("/api/students/{$studentUser->id}")
            ->assertStatus(200);

        $this->assertDatabaseMissing('users', ['id' => $studentUser->id]);
    }

    public function test_student_role_rejects_permissions_outside_evaluation_allowlist(): void
    {
        $admin = $this->grant($this->makeUser('admin', 'matrix-admin@test.com'), ['permission.manage']);
        $studentRole = Role::create(['name' => 'Student', 'guard_name' => 'web']);

        $studentViewId = Permission::firstOrCreate(['name' => 'student.view', 'guard_name' => 'web'])->id;
        $evalCreateId = Permission::firstOrCreate(['name' => 'evaluation.create', 'guard_name' => 'web'])->id;

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/roles/{$studentRole->id}/permissions", ['permissions' => [$studentViewId]])
            ->assertStatus(422);

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/roles/{$studentRole->id}/permissions", ['permissions' => [$evalCreateId]])
            ->assertStatus(200);

        $this->assertTrue($studentRole->fresh()->permissions->contains('name', 'evaluation.create'));
        $this->assertFalse($studentRole->fresh()->permissions->contains('name', 'student.view'));
    }

    public function test_student_user_rejects_direct_system_permissions(): void
    {
        $admin = $this->grant($this->makeUser('admin', 'direct-admin@test.com'), ['permission.manage']);
        $studentUser = $this->makeUser('student', 'policy-student@test.com');

        Permission::firstOrCreate(['name' => 'student.view', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'evaluation.create', 'guard_name' => 'web']);

        $denied = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/users/{$studentUser->id}/permissions", ['permissions' => ['student.view']]);
        $denied->assertStatus(422);
        $this->assertStringContainsString('evaluation permissions', $denied->json('message'));

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/users/{$studentUser->id}/permissions", ['permissions' => ['evaluation.create']])
            ->assertStatus(200);

        $this->assertTrue($studentUser->fresh()->getPermissionNames()->contains('evaluation.create'));
        $this->assertFalse($studentUser->fresh()->getPermissionNames()->contains('student.view'));
    }

    public function test_student_user_cannot_be_assigned_system_roles(): void
    {
        $admin = $this->grant($this->makeUser('admin', 'role-admin@test.com'), ['permission.manage']);
        $studentUser = $this->makeUser('student', 'role-student@test.com');

        $adminRole = Role::create(['name' => 'Admin', 'guard_name' => 'web']);
        $adminRole->permissions()->sync(
            Permission::firstOrCreate(['name' => 'student.view', 'guard_name' => 'web'])->id
        );
        Role::create(['name' => 'Student', 'guard_name' => 'web']);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/users/{$studentUser->id}/roles", ['roles' => ['Admin']])
            ->assertStatus(422);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/users/{$studentUser->id}/roles", ['roles' => ['Student']])
            ->assertStatus(200);

        $this->assertTrue($studentUser->fresh()->getRoleNames()->contains('Student'));
        $this->assertFalse($studentUser->fresh()->getRoleNames()->contains('Admin'));
    }

    public function test_completion_email_sent_after_submitting_last_evaluation(): void
    {
        Notification::fake();
        $this->openEvaluationPeriod();

        [, $section, $subject] = $this->makeAcademicFixture();
        $faculty = $this->makeFacultyWithUser('done-f@test.com');
        FacultyAssignment::create([
            'faculty_id' => $faculty->id,
            'subject_id' => $subject->id,
            'section_id' => $section->id,
            'academic_year' => '2024-2025',
            'semester' => '1st Semester',
            'year_level' => '1st',
        ]);

        $student = $this->grant(
            $this->makeUser('student', 'done-student@test.com'),
            ['evaluation.create', 'evaluation.submit']
        );
        $this->makeStudentRecord($student, $section);

        $this->actingAs($student, 'sanctum')
            ->postJson('/api/evaluations', $this->evaluationPayload($faculty))
            ->assertStatus(200);

        Notification::assertSentTo($student, EvaluationCompletedNotification::class);
    }

    public function test_completion_email_not_sent_while_evaluatees_remain(): void
    {
        Notification::fake();
        $this->openEvaluationPeriod();

        [, $section, $subject] = $this->makeAcademicFixture();
        $facultyA = $this->makeFacultyWithUser('remain-a@test.com');
        $facultyB = $this->makeFacultyWithUser('remain-b@test.com');

        foreach ([$facultyA, $facultyB] as $faculty) {
            FacultyAssignment::create([
                'faculty_id' => $faculty->id,
                'subject_id' => $subject->id,
                'section_id' => $section->id,
                'academic_year' => '2024-2025',
                'semester' => '1st Semester',
                'year_level' => '1st',
            ]);
        }

        $student = $this->grant(
            $this->makeUser('student', 'partial-student@test.com'),
            ['evaluation.create', 'evaluation.submit']
        );
        $this->makeStudentRecord($student, $section);

        $this->actingAs($student, 'sanctum')
            ->postJson('/api/evaluations', $this->evaluationPayload($facultyA))
            ->assertStatus(200);

        Notification::assertNotSentTo($student, EvaluationCompletedNotification::class);

        $this->actingAs($student, 'sanctum')
            ->postJson('/api/evaluations', $this->evaluationPayload($facultyB))
            ->assertStatus(200);

        Notification::assertSentToTimes($student, EvaluationCompletedNotification::class, 1);
    }
}
