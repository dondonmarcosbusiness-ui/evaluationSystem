<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Faculty;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Adding accounts is stricter than editing them:
 *  - ID number accepts digits and hyphens only (e.g. 2021-0001 or 202300120);
 *  - a supplied official email must be @neustcarranglan.ph.education;
 *  - editing an existing account keeps working for legacy records.
 * Blank email still falls back to car{id}@neustcarranglan.ph.education.
 */
class AccountInputValidationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::create([
            'firstname' => 'Admin',
            'lastname' => 'Tester',
            'email' => 'admin-validator@test.com',
            'password' => 'password',
            'role' => 'admin',
            'is_active' => true,
        ]);

        foreach (['faculty.create', 'faculty.edit', 'student.create'] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
            $user->givePermissionTo($name);
        }

        Role::firstOrCreate(['name' => 'Faculty', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Student', 'guard_name' => 'web']);

        return $user;
    }

    private function facultyPayload(array $overrides = []): array
    {
        return array_merge([
            'firstname' => 'Juan',
            'lastname' => 'Dela Cruz',
            'middlename' => '',
            'id_number' => '2021-0001',
            'email' => 'juan.delacruz@neustcarranglan.ph.education',
            'password' => 'password123',
            'department' => 'CIT',
            'course' => 'BSIT',
            'position' => 'Instructor',
        ], $overrides);
    }

    private function studentSection(): Section
    {
        $course = Course::create(['name' => 'BSIT ' . uniqid()]);

        return Section::create([
            'name' => '4A-' . uniqid(),
            'course_id' => $course->id,
        ]);
    }

    private function studentPayload(array $overrides = []): array
    {
        return array_merge([
            'firstname' => 'Maria',
            'lastname' => 'Santos',
            'middlename' => '',
            'id_number' => '202300120',
            'email' => 'maria.santos@neustcarranglan.ph.education',
            'password' => 'password123',
            'course' => 'BSIT',
            'section_id' => $this->studentSection()->id,
            'student_type' => 'regular',
            'year_level' => '1st',
        ], $overrides);
    }

    public function test_faculty_store_rejects_non_numeric_id_number(): void
    {
        $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/faculty', $this->facultyPayload(['id_number' => 'ABC-123']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['id_number']);
    }

    public function test_faculty_store_rejects_email_outside_neust_domain(): void
    {
        $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/faculty', $this->facultyPayload(['email' => 'juan@gmail.com']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_faculty_store_accepts_digits_and_hyphen_with_neust_email(): void
    {
        $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/faculty', $this->facultyPayload())
            ->assertStatus(200);

        $this->assertDatabaseHas('users', [
            'id_number' => '2021-0001',
            'email' => 'juan.delacruz@neustcarranglan.ph.education',
            'role' => 'faculty',
        ]);
    }

    public function test_faculty_store_blank_email_falls_back_to_default_domain(): void
    {
        $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/faculty', $this->facultyPayload(['email' => '']))
            ->assertStatus(200);

        $this->assertDatabaseHas('users', [
            'id_number' => '2021-0001',
            'email' => 'car2021-0001@neustcarranglan.ph.education',
        ]);
    }

    public function test_faculty_update_still_accepts_legacy_id_and_email(): void
    {
        $user = User::create([
            'firstname' => 'Legacy',
            'lastname' => 'Faculty',
            'id_number' => 'dfgdgdgd',
            'email' => 'legacy.faculty@example.com',
            'password' => 'password',
            'role' => 'faculty',
            'is_active' => true,
        ]);
        $faculty = Faculty::create([
            'user_id' => $user->id,
            'department' => 'CIT',
            'course' => 'BSIT',
            'position' => 'Instructor',
        ]);

        $this->actingAs($this->admin(), 'sanctum')
            ->putJson('/api/faculty/' . $faculty->id, [
                'firstname' => 'Legacy',
                'lastname' => 'Faculty',
                'middlename' => '',
                'id_number' => 'dfgdgdgd',
                'email' => 'legacy.faculty@example.com',
                'department' => 'CIT',
                'course' => 'BSIT',
                'position' => 'Instructor',
            ])
            ->assertStatus(200);
    }

    public function test_student_store_rejects_non_numeric_id_number(): void
    {
        $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/students', $this->studentPayload(['id_number' => '2021-ABCD']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['id_number']);
    }

    public function test_student_store_rejects_email_outside_neust_domain(): void
    {
        $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/students', $this->studentPayload(['email' => 'maria@yahoo.com']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_student_store_rejects_trailing_hyphen_in_id_number(): void
    {
        $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/students', $this->studentPayload(['id_number' => '2023-']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['id_number']);
    }

    public function test_student_store_accepts_valid_id_and_neust_email(): void
    {
        $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/students', $this->studentPayload())
            ->assertStatus(200);

        $this->assertDatabaseHas('users', [
            'id_number' => '202300120',
            'email' => 'maria.santos@neustcarranglan.ph.education',
            'role' => 'student',
        ]);
    }
}
