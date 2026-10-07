<?php

namespace Tests\Feature;

use App\Jobs\NotifyEvaluationWindowJob;
use App\Models\Course;
use App\Models\Evaluation;
use App\Models\EvaluationSchedule;
use App\Models\Faculty;
use App\Models\FacultyAssignment;
use App\Models\Permission;
use App\Models\Question;
use App\Models\Category;
use App\Models\Section;
use App\Models\Setting;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Services\EvaluationScheduleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Business rules:
 *  - the admin controls the evaluation window per department (or for all
 *    departments with one default row), with date windows + a manual override;
 *  - while no schedule rows exist the legacy `evaluation_status` switch keeps
 *    working exactly as before;
 *  - once schedules exist, a department without its own row follows the
 *    default row (and is closed when there is no default row);
 *  - opening/closing a window notifies only students affected by that change.
 */
class EvaluationScheduleTest extends TestCase
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

    private function makeAdmin(): User
    {
        return $this->grant($this->makeUser('admin', 'schedule-admin@test.com'), ['settings.manage']);
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

    private function makeFaculty(string $email, string $department): Faculty
    {
        $user = $this->makeUser('faculty', $email);

        return Faculty::create([
            'user_id' => $user->id,
            'department' => $department,
            'course' => 'BSIT',
            'position' => 'Instructor',
        ]);
    }

    private function assign(Faculty $faculty, Section $section, Subject $subject): void
    {
        FacultyAssignment::create([
            'faculty_id' => $faculty->id,
            'subject_id' => $subject->id,
            'section_id' => $section->id,
            'academic_year' => '2024-2025',
            'semester' => '1st Semester',
            'year_level' => '1st',
        ]);
    }

    private function activePeriod(): void
    {
        Setting::create(['key' => 'active_semester', 'value' => '1st Semester']);
        Setting::create(['key' => 'active_academic_year', 'value' => '2024-2025']);
        Cache::flush();
    }

    /** Record the current window state as the notification baseline. */
    private function seedSnapshot(): void
    {
        $statuses = app(EvaluationScheduleService::class)->computeUniverseStatuses();
        Setting::updateOrCreate(
            ['key' => EvaluationScheduleService::SNAPSHOT_KEY],
            ['value' => $statuses]
        );
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

    /** Student with an assignment to every given faculty member. */
    private function makeEvaluatingStudent(User $studentUser, Section $section, Subject $subject, array $faculty): void
    {
        $this->makeStudentRecord($studentUser, $section);
        foreach ($faculty as $member) {
            $this->assign($member, $section, $subject);
        }
    }

    public function test_schedule_management_requires_settings_permission(): void
    {
        $student = $this->makeUser('student', 'no-schedule-student@test.com');
        $faculty = $this->makeUser('faculty', 'no-schedule-faculty@test.com');

        $this->actingAs($student, 'sanctum')->getJson('/api/evaluation-schedules')->assertStatus(403);
        $this->actingAs($faculty, 'sanctum')->getJson('/api/evaluation-schedules')->assertStatus(403);

        $admin = $this->makeAdmin();
        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/evaluation-schedules')->assertStatus(200);

        $response->assertJsonStructure(['schedules', 'departments', 'global_status', 'legacy_mode']);
        $response->assertJson(['legacy_mode' => true]);
        $this->assertContains('General Education', $response->json('departments'));
    }

    public function test_store_creates_default_schedule_and_rejects_a_second_one(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/evaluation-schedules', ['status' => 'open'])
            ->assertStatus(201)
            ->assertJsonPath('schedule.is_global', true)
            ->assertJsonPath('schedule.effective_status', 'open')
            ->assertJsonPath('global_status', 'open');

        $this->assertDatabaseHas('evaluation_schedules', ['department' => null, 'status' => 'open']);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/evaluation-schedules', ['status' => 'open'])
            ->assertStatus(422);
    }

    public function test_store_validates_department_dates_and_status(): void
    {
        $this->makeAcademicFixture();
        $admin = $this->makeAdmin();

        // Department must exist in the Course List (plus General Education).
        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/evaluation-schedules', ['department' => 'Unknown Dept', 'status' => 'open'])
            ->assertStatus(422);

        // A scheduled window needs at least one bound.
        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/evaluation-schedules', ['status' => 'scheduled'])
            ->assertStatus(422);

        // End must be after start.
        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/evaluation-schedules', [
                'status' => 'scheduled',
                'starts_at' => '2026-10-05 08:00',
                'ends_at' => '2026-10-01 17:00',
            ])
            ->assertStatus(422);

        // Status is one of the three modes.
        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/evaluation-schedules', ['status' => 'whenever'])
            ->assertStatus(422);

        // One schedule per department.
        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/evaluation-schedules', ['department' => 'CIT', 'status' => 'open'])
            ->assertStatus(201);
        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/evaluation-schedules', ['department' => 'CIT', 'status' => 'closed'])
            ->assertStatus(422);
    }

    public function test_legacy_switch_still_gates_everything_while_no_schedules_exist(): void
    {
        [, $section, $subject] = $this->makeAcademicFixture();
        $faculty = $this->makeFaculty('legacy-faculty@test.com', 'CIT');

        $student = $this->grant(
            $this->makeUser('student', 'legacy-student@test.com'),
            ['evaluation.create', 'evaluation.submit']
        );
        $this->makeEvaluatingStudent($student, $section, $subject, [$faculty]);

        // Open — exactly the pre-scheduling behaviour.
        Setting::create(['key' => 'evaluation_status', 'value' => 'open']);
        $this->activePeriod();

        $this->actingAs($student, 'sanctum')
            ->getJson('/api/evaluations/evaluatees')
            ->assertStatus(200)
            ->assertJsonCount(1);

        $this->actingAs($student, 'sanctum')
            ->postJson('/api/evaluations', $this->evaluationPayload($faculty))
            ->assertStatus(200);

        // Closed — list empties and submission is refused.
        Evaluation::query()->delete();
        Setting::where('key', 'evaluation_status')->first()->update(['value' => 'closed']);
        Setting::forgetCache();

        $this->actingAs($student, 'sanctum')
            ->getJson('/api/evaluations/evaluatees')
            ->assertStatus(200)
            ->assertJsonCount(0);

        $this->actingAs($student, 'sanctum')
            ->postJson('/api/evaluations', $this->evaluationPayload($faculty))
            ->assertStatus(403);
    }

    public function test_per_department_window_filters_evaluatees_and_blocks_submission(): void
    {
        Queue::fake();
        [, $section, $subject] = $this->makeAcademicFixture();
        $citFaculty = $this->makeFaculty('cit-faculty@test.com', 'CIT');
        $genEdFaculty = $this->makeFaculty('gened-faculty@test.com', 'General Education');

        $student = $this->grant(
            $this->makeUser('student', 'mixed-student@test.com'),
            ['evaluation.create', 'evaluation.submit']
        );
        $this->makeEvaluatingStudent($student, $section, $subject, [$citFaculty, $genEdFaculty]);
        $this->activePeriod();

        // Default closed, CIT open.
        EvaluationSchedule::create(['department' => null, 'status' => 'closed']);
        EvaluationSchedule::create(['department' => 'CIT', 'status' => 'open']);

        $this->actingAs($student, 'sanctum')
            ->getJson('/api/evaluations/evaluatees')
            ->assertStatus(200)
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $citFaculty->id);

        // The closed department is refused at submission time too.
        $this->actingAs($student, 'sanctum')
            ->postJson('/api/evaluations', $this->evaluationPayload($genEdFaculty))
            ->assertStatus(403);

        $this->actingAs($student, 'sanctum')
            ->postJson('/api/evaluations', $this->evaluationPayload($citFaculty))
            ->assertStatus(200);
    }

    public function test_scheduled_window_opens_and_closes_with_time(): void
    {
        Queue::fake();
        [, $section, $subject] = $this->makeAcademicFixture();
        $faculty = $this->makeFaculty('window-faculty@test.com', 'CIT');

        $student = $this->grant(
            $this->makeUser('student', 'window-student@test.com'),
            ['evaluation.create', 'evaluation.submit']
        );
        $this->makeEvaluatingStudent($student, $section, $subject, [$faculty]);
        $this->activePeriod();

        EvaluationSchedule::create([
            'department' => null,
            'status' => 'scheduled',
            'starts_at' => '2026-10-01 08:00',
            'ends_at' => '2026-10-07 17:00',
        ]);

        try {
            Carbon::setTestNow('2026-09-30 12:00');
            $this->actingAs($student, 'sanctum')->getJson('/api/evaluations/evaluatees')->assertJsonCount(0);

            Carbon::setTestNow('2026-10-03 12:00');
            $this->actingAs($student, 'sanctum')->getJson('/api/evaluations/evaluatees')->assertJsonCount(1);

            Carbon::setTestNow('2026-10-08 12:00');
            $this->actingAs($student, 'sanctum')->getJson('/api/evaluations/evaluatees')->assertJsonCount(0);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_manual_override_wins_over_the_dates(): void
    {
        Queue::fake();
        [, $section, $subject] = $this->makeAcademicFixture();
        $faculty = $this->makeFaculty('override-faculty@test.com', 'CIT');

        $student = $this->grant(
            $this->makeUser('student', 'override-student@test.com'),
            ['evaluation.create', 'evaluation.submit']
        );
        $this->makeEvaluatingStudent($student, $section, $subject, [$faculty]);
        $this->activePeriod();

        try {
            Carbon::setTestNow('2026-10-10 12:00'); // window already ended

            // Force open despite a past end date.
            EvaluationSchedule::create([
                'department' => null,
                'status' => 'open',
                'starts_at' => '2026-10-01 08:00',
                'ends_at' => '2026-10-07 17:00',
            ]);
            $this->actingAs($student, 'sanctum')->getJson('/api/evaluations/evaluatees')->assertJsonCount(1);

            // Force closed despite a valid window.
            EvaluationSchedule::query()->update(['status' => 'closed']);
            $this->actingAs($student, 'sanctum')->getJson('/api/evaluations/evaluatees')->assertJsonCount(0);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_department_without_a_schedule_follows_the_default_row(): void
    {
        Queue::fake();
        [, $section, $subject] = $this->makeAcademicFixture();
        $citFaculty = $this->makeFaculty('fallback-cit@test.com', 'CIT');
        $genEdFaculty = $this->makeFaculty('fallback-gened@test.com', 'General Education');

        $student = $this->grant(
            $this->makeUser('student', 'fallback-student@test.com'),
            ['evaluation.create', 'evaluation.submit']
        );
        $this->makeEvaluatingStudent($student, $section, $subject, [$citFaculty, $genEdFaculty]);
        $this->activePeriod();

        // No CIT row → both follow the open default.
        EvaluationSchedule::create(['department' => null, 'status' => 'open']);
        $this->actingAs($student, 'sanctum')->getJson('/api/evaluations/evaluatees')->assertJsonCount(2);

        // Adding a CIT override closes only CIT.
        EvaluationSchedule::create(['department' => 'CIT', 'status' => 'closed']);
        $response = $this->actingAs($student, 'sanctum')->getJson('/api/evaluations/evaluatees')->assertJsonCount(1);
        $response->assertJsonPath('0.id', $genEdFaculty->id);
    }

    public function test_status_endpoint_and_settings_reflect_the_effective_window(): void
    {
        $student = $this->makeUser('student', 'status-student@test.com');

        // Legacy mode: reflects the stored switch.
        Setting::create(['key' => 'evaluation_status', 'value' => 'open']);
        Cache::flush();

        $this->actingAs($student, 'sanctum')
            ->getJson('/api/evaluation-schedules/status')
            ->assertStatus(200)
            ->assertJson(['status' => 'open']);

        $this->actingAs($student, 'sanctum')
            ->getJson('/api/settings')
            ->assertStatus(200)
            ->assertJsonPath('evaluation_status', 'open');

        // Schedule mode: closed default reported everywhere.
        Setting::updateOrCreate(['key' => 'evaluation_status'], ['value' => 'closed']);
        EvaluationSchedule::create(['department' => null, 'status' => 'closed']);
        Cache::flush();

        $this->actingAs($student, 'sanctum')
            ->getJson('/api/evaluation-schedules/status')
            ->assertJson(['status' => 'closed']);
        $this->actingAs($student, 'sanctum')
            ->getJson('/api/settings')
            ->assertJsonPath('evaluation_status', 'closed');

        // Any open schedule turns the institution-wide status on.
        EvaluationSchedule::create(['department' => 'CIT', 'status' => 'open']);
        $this->actingAs($student, 'sanctum')
            ->getJson('/api/evaluation-schedules/status')
            ->assertJson(['status' => 'open']);
    }

    public function test_settings_save_cannot_overwrite_scheduling_keys(): void
    {
        $admin = $this->makeAdmin();
        Setting::create(['key' => 'evaluation_status', 'value' => 'closed']);
        Setting::create(['key' => 'active_semester', 'value' => '1st Semester']);
        Cache::flush();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/settings', [
                'settings' => [
                    'evaluation_status' => 'open',
                    EvaluationScheduleService::SNAPSHOT_KEY => ['CIT' => 'open'],
                    'active_semester' => '2nd Semester',
                ],
            ])
            ->assertStatus(200);

        $this->assertSame('closed', Setting::where('key', 'evaluation_status')->first()->value);
        $this->assertSame(
            ['General Education' => 'closed'],
            Setting::where('key', EvaluationScheduleService::SNAPSHOT_KEY)->first()->value
        );
        $this->assertSame('2nd Semester', Setting::where('key', 'active_semester')->first()->value);
    }

    public function test_changing_a_window_notifies_only_the_affected_departments(): void
    {
        Queue::fake();
        $this->makeAcademicFixture();
        $admin = $this->makeAdmin();
        $this->activePeriod();
        $this->seedSnapshot(); // steady state: everything closed

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/evaluation-schedules', ['status' => 'open'])
            ->assertStatus(201);

        Queue::assertPushed(
            NotifyEvaluationWindowJob::class,
            fn (NotifyEvaluationWindowJob $job) => $job->event === 'open'
                && in_array('CIT', $job->departments, true)
                && in_array('General Education', $job->departments, true)
        );
        Queue::assertPushed(NotifyEvaluationWindowJob::class, 1);

        // Saving the same state again must not re-notify.
        $schedule = EvaluationSchedule::query()->whereNull('department')->firstOrFail();
        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/evaluation-schedules/{$schedule->id}", ['status' => 'open'])
            ->assertStatus(200);

        Queue::assertPushed(NotifyEvaluationWindowJob::class, 1);

        // Closing it again notifies the close event.
        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/evaluation-schedules/{$schedule->id}", ['status' => 'closed'])
            ->assertStatus(200);

        Queue::assertPushed(NotifyEvaluationWindowJob::class, 2);
        Queue::assertPushed(
            NotifyEvaluationWindowJob::class,
            fn (NotifyEvaluationWindowJob $job) => $job->event === 'closed'
                && in_array('CIT', $job->departments, true)
        );
    }

    public function test_deleting_a_department_override_notifies_the_revert(): void
    {
        Queue::fake();
        $this->makeAcademicFixture();
        $admin = $this->makeAdmin();
        $this->activePeriod();

        $default = EvaluationSchedule::create(['department' => null, 'status' => 'closed']);
        $cit = EvaluationSchedule::create(['department' => 'CIT', 'status' => 'open']);
        $this->seedSnapshot(); // steady state: CIT open, everything else closed

        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/evaluation-schedules/{$cit->id}")
            ->assertStatus(200)
            ->assertJsonPath('global_status', 'closed');

        Queue::assertPushed(
            NotifyEvaluationWindowJob::class,
            fn (NotifyEvaluationWindowJob $job) => $job->event === 'closed'
                && $job->departments === ['CIT']
        );

        // Removing an OPEN default row closes every department that relied on it.
        Queue::fake();
        $default->update(['status' => 'open']);
        $this->seedSnapshot();

        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/evaluation-schedules/{$default->id}")
            ->assertStatus(200)
            ->assertJsonPath('global_status', 'closed');

        Queue::assertPushed(
            NotifyEvaluationWindowJob::class,
            fn (NotifyEvaluationWindowJob $job) => $job->event === 'closed'
                && in_array('CIT', $job->departments, true)
                && in_array('General Education', $job->departments, true)
        );
    }
}
