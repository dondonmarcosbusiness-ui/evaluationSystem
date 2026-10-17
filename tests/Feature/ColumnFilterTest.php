<?php

namespace Tests\Feature;

use App\Models\Faculty;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ColumnFilterTest extends TestCase
{
    use RefreshDatabase;

    private function grant(User $user, string $permission): User
    {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        $user->givePermissionTo($permission);

        return $user;
    }

    private function makeAdmin(): User
    {
        return User::create([
            'firstname' => 'Root',
            'lastname' => 'Admin',
            'email' => 'col-filter-admin@test.com',
            'id_number' => 'ADMIN-CF',
            'password' => 'password',
            'role' => 'admin',
            'is_active' => true,
        ]);
    }

    private function makeFaculty(
        string $idNumber,
        string $email,
        string $lastName,
        string $department,
        string $course,
        string $position,
        bool $active = true
    ): Faculty {
        $user = User::create([
            'firstname' => 'Fac',
            'lastname' => $lastName,
            'email' => $email,
            'id_number' => $idNumber,
            'password' => 'password',
            'role' => 'faculty',
            'is_active' => $active,
        ]);

        return Faculty::create([
            'user_id' => $user->id,
            'department' => $department,
            'course' => $course,
            'position' => $position,
        ]);
    }

    private function makeStudent(
        string $idNumber,
        string $email,
        string $lastName,
        string $type,
        string $yearLevel,
        bool $active = true
    ): User {
        $user = User::create([
            'firstname' => 'Stu',
            'lastname' => $lastName,
            'email' => $email,
            'id_number' => $idNumber,
            'password' => 'password',
            'role' => 'student',
            'is_active' => $active,
        ]);

        $user->student()->create([
            'course' => 'BSIT',
            'section' => 'IT-1A',
            'student_type' => $type,
            'year_level' => $yearLevel,
        ]);

        return $user;
    }

    /** Record ids returned by a paginated list response. */
    private function ids($response): array
    {
        return collect($response->json('data'))->pluck('id')->all();
    }

    public function test_faculty_list_filters_by_id_number_and_name_columns(): void
    {
        $alpha = $this->makeFaculty('2026-0001', 'alpha@test.com', 'Alpha', 'CIT', 'BSIT', 'Instructor I');
        $beta = $this->makeFaculty('2026-0002', 'beta@test.com', 'Beta', 'CIT', 'BSIT', 'Professor I');

        $admin = $this->grant($this->makeAdmin(), 'faculty.view');

        $byId = $this->actingAs($admin, 'sanctum')->getJson('/api/faculty?id_number=0002');
        $byId->assertOk();
        $this->assertSame([$beta->id], $this->ids($byId));

        $byName = $this->actingAs($admin, 'sanctum')->getJson('/api/faculty?name=Beta');
        $byName->assertOk();
        $this->assertSame([$beta->id], $this->ids($byName));

        $byEmail = $this->actingAs($admin, 'sanctum')->getJson('/api/faculty?email=alpha@');
        $this->assertSame([$alpha->id], $this->ids($byEmail));
    }

    public function test_faculty_list_filters_by_position_course_department_and_status(): void
    {
        $match = $this->makeFaculty('2026-1001', 'match@test.com', 'Match', 'CIT', 'BSIT', 'Instructor II');
        $this->makeFaculty('2026-1002', 'other@test.com', 'Other', 'HM', 'BSHM', 'Professor I');
        $inactive = $this->makeFaculty('2026-1003', 'off@test.com', 'Off', 'CIT', 'BSIT', 'Instructor II', false);

        $admin = $this->grant($this->makeAdmin(), 'faculty.view');

        $byPosition = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/faculty?position=Instructor%20II&status=active');
        $byPosition->assertOk();
        $this->assertSame([$match->id], $this->ids($byPosition));

        $byCourse = $this->actingAs($admin, 'sanctum')->getJson('/api/faculty?course=BSIT');
        $this->assertEqualsCanonicalizing([$match->id, $inactive->id], $this->ids($byCourse));

        $byDepartment = $this->actingAs($admin, 'sanctum')->getJson('/api/faculty?department=HM');
        $this->assertCount(1, $byDepartment->json('data'));
        $this->assertSame('HM', $byDepartment->json('data.0.department'));

        $byStatus = $this->actingAs($admin, 'sanctum')->getJson('/api/faculty?status=inactive');
        $this->assertSame([$inactive->id], $this->ids($byStatus));
    }

    public function test_faculty_list_and_export_share_the_same_column_scoping(): void
    {
        $shared = $this->makeFaculty('2026-2001', 'shared@test.com', 'Shared', 'CIT', 'BSIT', 'Instructor I');
        $this->makeFaculty('2026-2002', 'skipped@test.com', 'Skipped', 'HM', 'BSHM', 'Instructor I');

        $admin = $this->grant($this->makeAdmin(), 'faculty.view');

        $query = http_build_query(['name' => 'Shared', 'position' => 'Instructor I']);

        $list = $this->actingAs($admin, 'sanctum')->getJson('/api/faculty?' . $query);
        $list->assertOk();
        $this->assertSame([$shared->id], $this->ids($list));

        $csv = $this->actingAs($admin, 'sanctum')->get('/api/faculty/export?' . $query);
        $csv->assertOk();
        $this->assertStringContainsString('2026-2001', $csv->streamedContent());
        $this->assertStringNotContainsString('2026-2002', $csv->streamedContent());
    }

    public function test_student_list_filters_by_year_level_and_status(): void
    {
        $first = $this->makeStudent('2026-3001', 'first@test.com', 'First', 'regular', '1st');
        $this->makeStudent('2026-3002', 'second@test.com', 'Second', 'regular', '2nd');
        $off = $this->makeStudent('2026-3003', 'off@test.com', 'Off', 'irregular', '1st', false);

        $admin = $this->grant($this->makeAdmin(), 'student.view');

        $byYear = $this->actingAs($admin, 'sanctum')->getJson('/api/students?year_level=1st');
        $byYear->assertOk();
        $this->assertEqualsCanonicalizing([$first->id, $off->id], $this->ids($byYear));

        $activeFirstYear = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/students?year_level=1st&status=active');
        $this->assertSame([$first->id], $this->ids($activeFirstYear));

        $byStatus = $this->actingAs($admin, 'sanctum')->getJson('/api/students?status=inactive');
        $this->assertSame([$off->id], $this->ids($byStatus));
    }

    public function test_student_list_filters_by_id_number_name_and_email_columns(): void
    {
        $ada = $this->makeStudent('2026-4001', 'ada@test.com', 'Adams', 'regular', '1st');
        $this->makeStudent('2026-4002', 'bob@test.com', 'Baker', 'regular', '2nd');

        $admin = $this->grant($this->makeAdmin(), 'student.view');

        $byId = $this->actingAs($admin, 'sanctum')->getJson('/api/students?id_number=4001');
        $this->assertSame([$ada->id], $this->ids($byId));

        $byName = $this->actingAs($admin, 'sanctum')->getJson('/api/students?name=Adams');
        $this->assertSame([$ada->id], $this->ids($byName));

        $byEmail = $this->actingAs($admin, 'sanctum')->getJson('/api/students?email=ada@');
        $this->assertSame([$ada->id], $this->ids($byEmail));
    }

    public function test_student_list_filters_recalculate_the_reported_total(): void
    {
        $this->makeStudent('2026-5001', 'one@test.com', 'One', 'regular', '1st');
        $this->makeStudent('2026-5002', 'two@test.com', 'Two', 'irregular', '4th');

        $admin = $this->grant($this->makeAdmin(), 'student.view');

        $all = $this->actingAs($admin, 'sanctum')->getJson('/api/students');
        $all->assertOk();
        $this->assertSame(2, $all->json('total'));

        $filtered = $this->actingAs($admin, 'sanctum')->getJson('/api/students?student_type=irregular');
        $this->assertSame(1, $filtered->json('total'));
        $this->assertSame('4th', $filtered->json('data.0.student.year_level'));
    }

    public function test_faculty_list_sorts_by_user_and_faculty_columns(): void
    {
        $this->makeFaculty('2026-6001', 'zeta@test.com', 'Zeta', 'CIT', 'BSIT', 'Instructor I');
        $alpha = $this->makeFaculty('2026-6002', 'alpha@test.com', 'Alpha', 'HM', 'BSHM', 'Professor I');

        $admin = $this->grant($this->makeAdmin(), 'faculty.view');

        $byName = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/faculty?sort_by=name&sort_dir=asc');
        $byName->assertOk();
        $this->assertSame('Alpha', $byName->json('data.0.user.lastname'));

        $byNameDesc = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/faculty?sort_by=name&sort_dir=desc');
        $this->assertSame('Zeta', $byNameDesc->json('data.0.user.lastname'));

        $byDepartment = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/faculty?sort_by=department&sort_dir=desc');
        $this->assertSame('HM', $byDepartment->json('data.0.department'));
        $this->assertSame($alpha->user_id, $byDepartment->json('data.0.user_id'));

        $byIdNumber = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/faculty?sort_by=id_number&sort_dir=desc');
        $this->assertSame('2026-6002', $byIdNumber->json('data.0.user.id_number'));
    }

    public function test_student_list_sorts_by_user_and_student_columns(): void
    {
        $this->makeStudent('2026-6101', 'zeta@test.com', 'Zeta', 'regular', '4th');
        $this->makeStudent('2026-6102', 'alpha@test.com', 'Alpha', 'irregular', '1st');

        $admin = $this->grant($this->makeAdmin(), 'student.view');

        $byName = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/students?sort_by=name&sort_dir=asc');
        $byName->assertOk();
        $this->assertSame('Alpha', $byName->json('data.0.lastname'));

        $byYear = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/students?sort_by=year_level&sort_dir=asc');
        $this->assertSame('1st', $byYear->json('data.0.student.year_level'));

        $byType = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/students?sort_by=student_type&sort_dir=desc');
        $this->assertSame('regular', $byType->json('data.0.student.student_type'));

        // Unknown sort keys are ignored rather than erroring out.
        $ignored = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/students?sort_by=DROP%20TABLE&sort_dir=asc');
        $ignored->assertOk();
        $this->assertCount(2, $ignored->json('data'));
    }

    public function test_recent_filter_limits_accounts_created_within_the_window(): void
    {
        $freshFaculty = $this->makeFaculty('2026-7001', 'fresh@test.com', 'Fresh', 'CIT', 'BSIT', 'Instructor I');
        $oldFaculty = $this->makeFaculty('2026-7002', 'old@test.com', 'Old', 'CIT', 'BSIT', 'Instructor I');
        $this->backdate($oldFaculty, 40);

        $freshStudent = $this->makeStudent('2026-7101', 'fresh-stu@test.com', 'FreshStu', 'regular', '1st');
        $oldStudent = $this->makeStudent('2026-7102', 'old-stu@test.com', 'OldStu', 'regular', '1st');
        $this->backdateStudent($oldStudent, 40);

        $admin = $this->grant($this->makeAdmin(), 'faculty.view');
        $admin = $this->grant($admin, 'student.view');

        $faculty = $this->actingAs($admin, 'sanctum')->getJson('/api/faculty?recent=7');
        $faculty->assertOk();
        $this->assertSame([$freshFaculty->id], $this->ids($faculty));

        $students = $this->actingAs($admin, 'sanctum')->getJson('/api/students?recent=7');
        $students->assertOk();
        $this->assertSame([$freshStudent->id], $this->ids($students));

        // Export honours the same window.
        $csv = $this->actingAs($admin, 'sanctum')->get('/api/faculty/export?recent=7');
        $csv->assertOk();
        $this->assertStringContainsString('2026-7001', $csv->streamedContent());
        $this->assertStringNotContainsString('2026-7002', $csv->streamedContent());
    }

    /** Push a record's created_at (and its user's) into the past. */
    private function backdate(Faculty $faculty, int $days): void
    {
        $stamp = now()->subDays($days);
        $faculty->forceFill(['created_at' => $stamp])->save();
        $faculty->user->forceFill(['created_at' => $stamp])->save();
    }

    private function backdateStudent(User $student, int $days): void
    {
        $student->forceFill(['created_at' => now()->subDays($days)])->save();
    }
}
