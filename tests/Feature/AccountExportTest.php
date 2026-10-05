<?php

namespace Tests\Feature;

use App\Models\Faculty;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountExportTest extends TestCase
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

    private function makeUser(string $role, string $email, string $idNumber = null): User
    {
        return User::create([
            'firstname' => 'Test',
            'lastname' => ucfirst($role),
            'email' => $email,
            'id_number' => $idNumber,
            'password' => 'password',
            'role' => $role,
            'is_active' => true,
        ]);
    }

    private function makeFaculty(string $email, string $department, string $idNumber): Faculty
    {
        $user = $this->makeUser('faculty', $email, $idNumber);

        return Faculty::create([
            'user_id' => $user->id,
            'department' => $department,
            'course' => 'BSIT',
            'position' => 'Instructor',
        ]);
    }

    private function makeStudent(string $email, string $idNumber, string $type): User
    {
        $user = $this->makeUser('student', $email, $idNumber);
        $user->student()->create([
            'course' => 'BSIT',
            'section' => 'IT-1A',
            'student_type' => $type,
            'year_level' => '1st Year',
        ]);

        return $user;
    }

    /** First line of the download (BOM stripped), parsed as CSV. */
    private function csvHeader(string $csv): array
    {
        $firstLine = strtok(substr($csv, 3), "\n");

        return str_getcsv($firstLine);
    }

    public function test_faculty_export_requires_faculty_view(): void
    {
        $user = $this->makeUser('admin', 'export-admin@test.com');

        $this->actingAs($user, 'sanctum')
            ->get('/api/faculty/export')
            ->assertStatus(403);

        $this->grant($user, ['faculty.view']);

        $this->actingAs($user, 'sanctum')
            ->get('/api/faculty/export')
            ->assertStatus(200);
    }

    public function test_faculty_export_streams_import_ready_csv_and_applies_filters(): void
    {
        $this->makeFaculty('cit@test.com', 'CIT', '2026-0001');
        $this->makeFaculty('hm@test.com', 'HM', '2026-0002');

        $admin = $this->grant($this->makeUser('admin', 'fac-export@test.com'), ['faculty.view']);

        $response = $this->actingAs($admin, 'sanctum')->get('/api/faculty/export?department=CIT');
        $response->assertStatus(200);

        $csv = $response->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertSame(
            ['id number', 'last name', 'first name', 'middle name', 'position', 'department', 'course', 'email', 'status'],
            $this->csvHeader($csv)
        );
        $this->assertStringContainsString('2026-0001', $csv);
        $this->assertStringContainsString('CIT', $csv);
        $this->assertStringNotContainsString('2026-0002', $csv);
    }

    public function test_student_export_requires_student_view(): void
    {
        $user = $this->makeUser('admin', 'student-export-admin@test.com');

        $this->actingAs($user, 'sanctum')
            ->get('/api/students/export')
            ->assertStatus(403);

        $this->grant($user, ['student.view']);

        $this->actingAs($user, 'sanctum')
            ->get('/api/students/export')
            ->assertStatus(200);
    }

    public function test_student_export_streams_csv_and_applies_type_filter(): void
    {
        $this->makeStudent('regular@test.com', '2026-1001', 'regular');
        $this->makeStudent('irregular@test.com', '2026-1002', 'irregular');

        $admin = $this->grant($this->makeUser('admin', 'stu-export@test.com'), ['student.view']);

        $response = $this->actingAs($admin, 'sanctum')->get('/api/students/export?student_type=irregular');
        $response->assertStatus(200);

        $csv = $response->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertSame(
            ['id number', 'last name', 'first name', 'middle name', 'course', 'section', 'year level', 'student type', 'email', 'status'],
            $this->csvHeader($csv)
        );
        $this->assertStringContainsString('2026-1002', $csv);
        $this->assertStringContainsString('irregular', $csv);
        $this->assertStringNotContainsString('2026-1001', $csv);
    }
}
