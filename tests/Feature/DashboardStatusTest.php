<?php

namespace Tests\Feature;

use App\Models\Evaluation;
use App\Models\Faculty;
use App\Models\LoginLog;
use App\Models\Permission;
use App\Models\Setting;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardStatusTest extends TestCase
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

    /** @return array{0: User, 1: Student} */
    private function makeStudent(string $email, string $yearLevel, ?string $course = null): array
    {
        $user = $this->makeUser('student', $email);
        $student = Student::create([
            'user_id' => $user->id,
            'course' => $course,
            'year_level' => $yearLevel,
            'student_type' => 'regular',
        ]);

        return [$user, $student];
    }

    private function markOnline(User $user): void
    {
        $user->createToken('auth_token');
        $user->tokens()->where('name', 'auth_token')->update(['last_used_at' => now()]);
    }

    public function test_status_requires_dashboard_view(): void
    {
        $user = $this->makeUser('staff', uniqid('no-dash-') . '@test.com');

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/dashboard/status')
            ->assertStatus(403);

        $this->grant($user, ['dashboard.view']);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/dashboard/status')
            ->assertStatus(200);
    }

    public function test_online_students_are_grouped_by_department_and_year(): void
    {
        $admin = $this->grant($this->makeUser('admin', uniqid('on-a-') . '@test.com'), ['dashboard.view']);

        // Online student: has a token used within the window.
        [$onlineStudent] = $this->makeStudent(uniqid('on-') . '@test.com', '1st Year', 'BSIT');
        $this->markOnline($onlineStudent);

        // Offline student: account exists but never used the API.
        $this->makeStudent(uniqid('off-') . '@test.com', '2nd Year', 'BSIT');

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/dashboard/status')
            ->assertStatus(200);

        $online = $response->json('online_students');
        $this->assertSame(1, $online['total_online']);
        $this->assertSame(2, $online['total_students']);

        $firstYear = collect($online['by_year'])->firstWhere('year_level', '1st Year');
        $this->assertSame(1, $firstYear['online']);
        $this->assertSame(1, $firstYear['total']);

        $secondYear = collect($online['by_year'])->firstWhere('year_level', '2nd Year');
        $this->assertSame(0, $secondYear['online']);

        $department = collect($online['by_department'])->firstWhere('department', 'Unspecified');
        $this->assertSame(1, $department['online']);
        $this->assertSame(2, $department['total']);
    }

    public function test_status_reports_peak_today_and_never_lowers_it(): void
    {
        $admin = $this->grant($this->makeUser('admin', uniqid('pk-a-') . '@test.com'), ['dashboard.view']);

        [$student] = $this->makeStudent(uniqid('pk-') . '@test.com', '1st Year', 'BSIT');
        $this->markOnline($student);

        $online = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/dashboard/status')
            ->assertStatus(200)
            ->json('online_students');

        $this->assertSame(1, $online['total_online']);
        $this->assertSame(1, $online['peak_today']);
        $this->assertSame(now()->toDateString(), $online['peak_date']);

        // Presence drops to zero — the recorded peak must not be lowered.
        $this->travel(10)->minutes();

        $online = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/dashboard/status')
            ->assertStatus(200)
            ->json('online_students');

        $this->assertSame(0, $online['total_online']);
        $this->assertSame(1, $online['peak_today']);
    }

    public function test_sample_peak_command_records_current_count(): void
    {
        $this->makeStudent(uniqid('cmd-a-') . '@test.com', '1st Year', 'BSIT');
        [$online] = $this->makeStudent(uniqid('cmd-b-') . '@test.com', '2nd Year', 'BSIT');
        $this->markOnline($online);

        $this->artisan('online-peak:sample')
            ->assertSuccessful();

        $this->assertSame(
            1,
            \App\Models\StudentOnlinePeak::query()
                ->whereDate('date', now()->toDateString())
                ->value('peak'),
        );

        // A later sample with nobody online must not lower the peak.
        $this->travel(10)->minutes();
        $this->artisan('online-peak:sample')->assertSuccessful();

        $this->assertSame(
            1,
            \App\Models\StudentOnlinePeak::query()
                ->whereDate('date', now()->toDateString())
                ->value('peak'),
        );
    }

    public function test_online_students_use_course_department_when_available(): void
    {
        $admin = $this->grant($this->makeUser('admin', uniqid('on-b-') . '@test.com'), ['dashboard.view']);

        \App\Models\Course::create([
            'name' => 'BSIT',
            'department' => 'CIT',
            'subjects' => '[]',
            'sections' => '[]',
        ]);

        [$student] = $this->makeStudent(uniqid('cit-') . '@test.com', '3rd Year', 'BSIT');
        $this->markOnline($student);

        $online = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/dashboard/status')
            ->assertStatus(200)
            ->json('online_students');

        $cit = collect($online['by_department'])->firstWhere('department', 'CIT');
        $this->assertSame(1, $cit['online']);
    }

    public function test_evaluation_activity_metrics_are_returned(): void
    {
        $admin = $this->grant($this->makeUser('admin', uniqid('act-') . '@test.com'), ['dashboard.view']);
        $this->activatePeriod('1st Semester', '2025-2026');

        $facultyUser = $this->makeUser('faculty', uniqid('fac-') . '@test.com');
        $faculty = Faculty::create([
            'user_id' => $facultyUser->id,
            'department' => 'CIT',
            'course' => 'BSIT',
            'position' => 'Instructor',
        ]);
        [$student] = $this->makeStudent(uniqid('ev-') . '@test.com', '1st Year', 'BSIT');

        Evaluation::create([
            'student_id' => $student->id,
            'faculty_id' => $faculty->id,
            'evaluatee_type' => 'faculty',
            'evaluatee_id' => $faculty->id,
            'semester' => '1st Semester',
            'academic_year' => '2025-2026',
            'subject_code' => 'IT-SIA01',
            'comments' => 'Great',
        ]);

        $json = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/dashboard/status')
            ->assertStatus(200)
            ->json();

        // Submitted but never finished: the row exists, the flag does not.
        $this->assertSame(1, $json['evaluations_submitted']);
        $this->assertSame(0, $json['students_finished']);
        $this->assertCount(7, $json['series_labels']);
        $this->assertCount(7, $json['evaluations_submitted_series']);
        $this->assertArrayHasKey('students_submitted_series', $json);
    }

    public function test_students_finished_counts_only_completion_flag_for_active_period(): void
    {
        $admin = $this->grant($this->makeUser('admin', uniqid('fin-') . '@test.com'), ['dashboard.view']);
        $this->activatePeriod('1st Semester', '2025-2026');

        // Finished the active period.
        [$finished] = $this->makeStudent(uniqid('fin-a-') . '@test.com', '1st Year', 'BSIT');
        $this->markFinished($finished, '1st Semester', '2025-2026');

        // Finished a previous period only.
        [$stale] = $this->makeStudent(uniqid('fin-b-') . '@test.com', '1st Year', 'BSIT');
        $this->markFinished($stale, '2nd Semester', '2024-2025');

        // Submitted an evaluation but never finished.
        [$partial] = $this->makeStudent(uniqid('fin-c-') . '@test.com', '1st Year', 'BSIT');
        $facultyUser = $this->makeUser('faculty', uniqid('fin-fac-') . '@test.com');
        $faculty = Faculty::create([
            'user_id' => $facultyUser->id,
            'department' => 'CIT',
            'course' => 'BSIT',
            'position' => 'Instructor',
        ]);
        Evaluation::create([
            'student_id' => $partial->id,
            'faculty_id' => $faculty->id,
            'evaluatee_type' => 'faculty',
            'evaluatee_id' => $faculty->id,
            'semester' => '1st Semester',
            'academic_year' => '2025-2026',
            'subject_code' => 'IT-SIA01',
        ]);

        $json = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/dashboard/status')
            ->assertStatus(200)
            ->json();

        $this->assertSame(1, $json['students_finished']);
        $this->assertSame(1, $json['evaluations_submitted']);
    }

    private function activatePeriod(string $semester, string $academicYear): void
    {
        Setting::create(['key' => 'active_semester', 'value' => $semester]);
        Setting::create(['key' => 'active_academic_year', 'value' => $academicYear]);
        Setting::forgetCache();
    }

    private function markFinished(User $student, string $semester, string $academicYear): void
    {
        $student->evaluations_completed_at = now();
        $student->evaluations_completed_semester = $semester;
        $student->evaluations_completed_academic_year = $academicYear;
        $student->save();
    }

    public function test_access_error_figures_require_permission_manage(): void
    {
        $admin = $this->grant($this->makeUser('admin', uniqid('ae-a-') . '@test.com'), ['dashboard.view']);
        LoginLog::create([
            'login_identifier' => 'someone@test.com',
            'status' => 'failed',
            'reason' => 'invalid_credentials',
            'driver' => 'password',
        ]);

        $visible = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/dashboard/status')
            ->assertStatus(200)
            ->json();

        $this->assertFalse($visible['access_errors_visible']);
        $this->assertNull($visible['failed_logins']);

        $this->grant($admin, ['permission.manage']);

        $hidden = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/dashboard/status')
            ->assertStatus(200)
            ->json();

        $this->assertTrue($hidden['access_errors_visible']);
        $this->assertSame(1, $hidden['failed_logins']);
        $this->assertSame(1, array_sum($hidden['failed_logins_series']));
    }
}
