<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Permission;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class CourseSubjectImportTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): User
    {
        $user = User::create([
            'firstname' => 'Test',
            'lastname' => 'Admin',
            'email' => 'admin@test.com',
            'password' => 'password',
            'role' => 'admin',
            'is_active' => true,
        ]);
        Permission::firstOrCreate(['name' => 'manage_courses', 'guard_name' => 'web']);
        $user->givePermissionTo('manage_courses');

        return $user;
    }

    private function makeCourse(): Course
    {
        return Course::create(['name' => 'BSIT', 'department' => 'CIT']);
    }

    private function csvFile(string $contents): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'subj') . '.csv';
        file_put_contents($path, $contents);

        return new UploadedFile($path, 'subjects.csv', 'text/csv', null, true);
    }

    public function test_imports_subjects_with_per_row_years(): void
    {
        $admin = $this->makeAdmin();
        $course = $this->makeCourse();

        $res = $this->actingAs($admin, 'sanctum')->post(
            "/api/courses/{$course->id}/subjects/import",
            ['file' => $this->csvFile("code,subject,year level\nIT101,Programming,1st\nIT102,Databases,2nd\n")]
        );

        $res->assertOk();
        $this->assertSame(2, $res->json('imported'));
        $this->assertSame(0, $res->json('failed'));

        $this->assertDatabaseHas('subjects', [
            'course_id' => $course->id, 'code' => 'IT101', 'name' => 'Programming', 'year_level' => '1st',
        ]);
        $this->assertDatabaseHas('subjects', [
            'course_id' => $course->id, 'code' => 'IT102', 'name' => 'Databases', 'year_level' => '2nd',
        ]);
    }

    public function test_blank_code_is_stored_as_null(): void    {
        $admin = $this->makeAdmin();
        $course = $this->makeCourse();

        $res = $this->actingAs($admin, 'sanctum')->post(
            "/api/courses/{$course->id}/subjects/import",
            ['file' => $this->csvFile("code,subject,year level\n,Programming,1st\n")]
        );

        $res->assertOk();
        $this->assertSame(1, $res->json('imported'));
        $this->assertDatabaseHas('subjects', [
            'course_id' => $course->id, 'code' => null, 'name' => 'Programming', 'year_level' => '1st',
        ]);
    }

    public function test_utf8_bom_does_not_break_the_first_header(): void
    {
        $admin = $this->makeAdmin();
        $course = $this->makeCourse();

        // Our own CSV template (and Excel) prefix the file with a BOM.
        $res = $this->actingAs($admin, 'sanctum')->post(
            "/api/courses/{$course->id}/subjects/import",
            ['file' => $this->csvFile("\xEF\xBB\xBFcode,subject,year level\nIT101,Programming,1st\n")]
        );

        $res->assertOk();
        $this->assertSame(1, $res->json('imported'));
        $this->assertSame(0, $res->json('failed'));
        $this->assertDatabaseHas('subjects', [
            'course_id' => $course->id, 'code' => 'IT101', 'name' => 'Programming', 'year_level' => '1st',
        ]);
    }

    public function test_rows_without_year_inherit_request_year(): void
    {
        $admin = $this->makeAdmin();
        $course = $this->makeCourse();

        $res = $this->actingAs($admin, 'sanctum')->post(
            "/api/courses/{$course->id}/subjects/import",
            [
                'file' => $this->csvFile("code,subject\nIT101,Programming\n"),
                'year_level' => '3rd',
            ]
        );

        $res->assertOk();
        $this->assertSame(1, $res->json('imported'));
        $this->assertDatabaseHas('subjects', [
            'course_id' => $course->id, 'name' => 'Programming', 'year_level' => '3rd',
        ]);
    }

    public function test_rows_without_any_year_stay_untagged(): void
    {
        $admin = $this->makeAdmin();
        $course = $this->makeCourse();

        $this->actingAs($admin, 'sanctum')->post(
            "/api/courses/{$course->id}/subjects/import",
            ['file' => $this->csvFile("code,subject\nIT101,Programming\n")]
        )->assertOk();

        $this->assertDatabaseHas('subjects', [
            'course_id' => $course->id, 'name' => 'Programming', 'year_level' => null,
        ]);
    }

    public function test_invalid_year_and_blank_rows_only_fail_those_rows(): void
    {
        $admin = $this->makeAdmin();
        $course = $this->makeCourse();

        $res = $this->actingAs($admin, 'sanctum')->post(
            "/api/courses/{$course->id}/subjects/import",
            ['file' => $this->csvFile("code,subject,year level\nIT101,Programming,5th\nIT102,,1st\nIT103,Databases,2nd\n")]
        );

        $res->assertOk();
        $this->assertSame(1, $res->json('imported'));
        $this->assertSame(2, $res->json('failed'));
        $this->assertSame(1, Subject::where('course_id', $course->id)->count());
    }

    public function test_reupload_updates_without_duplicating_and_never_clears_year(): void
    {
        $admin = $this->makeAdmin();
        $course = $this->makeCourse();
        Subject::create(['course_id' => $course->id, 'name' => 'Programming', 'code' => 'IT101', 'year_level' => '1st']);

        $res = $this->actingAs($admin, 'sanctum')->post(
            "/api/courses/{$course->id}/subjects/import",
            ['file' => $this->csvFile("code,subject\nIT101,Programming\n")]
        );

        $res->assertOk();
        $this->assertSame(1, Subject::where('course_id', $course->id)->count());
        $this->assertDatabaseHas('subjects', [
            'course_id' => $course->id, 'name' => 'Programming', 'year_level' => '1st',
        ]);
    }

    public function test_missing_subject_header_returns_400(): void
    {
        $admin = $this->makeAdmin();
        $course = $this->makeCourse();

        $this->actingAs($admin, 'sanctum')->post(
            "/api/courses/{$course->id}/subjects/import",
            ['file' => $this->csvFile("code,name\nIT101,Programming\n")]
        )->assertStatus(400);

        $this->assertSame(0, Subject::where('course_id', $course->id)->count());
    }

    public function test_user_without_manage_courses_is_forbidden(): void
    {
        $user = User::create([
            'firstname' => 'Test',
            'lastname' => 'Faculty',
            'email' => 'faculty@test.com',
            'password' => 'password',
            'role' => 'faculty',
            'is_active' => true,
        ]);
        $course = $this->makeCourse();

        $this->actingAs($user, 'sanctum')->post(
            "/api/courses/{$course->id}/subjects/import",
            ['file' => $this->csvFile("code,subject\nIT101,Programming\n")]
        )->assertForbidden();
    }

    public function test_bulk_delete_removes_only_selected_subjects(): void
    {
        $admin = $this->makeAdmin();
        $course = $this->makeCourse();
        $keep = Subject::create(['course_id' => $course->id, 'name' => 'Keep', 'code' => 'IT100', 'year_level' => '1st']);
        $del1 = Subject::create(['course_id' => $course->id, 'name' => 'Gone One', 'code' => 'IT101', 'year_level' => '1st']);
        $del2 = Subject::create(['course_id' => $course->id, 'name' => 'Gone Two', 'code' => 'IT102', 'year_level' => '2nd']);

        $res = $this->actingAs($admin, 'sanctum')->post(
            "/api/courses/{$course->id}/subjects/bulk-delete",
            ['ids' => [$del1->id, $del2->id]]
        );

        $res->assertOk();
        $this->assertSame(2, $res->json('deleted'));
        $this->assertDatabaseMissing('subjects', ['id' => $del1->id]);
        $this->assertDatabaseMissing('subjects', ['id' => $del2->id]);
        $this->assertDatabaseHas('subjects', ['id' => $keep->id]);
    }

    public function test_bulk_delete_ignores_ids_from_other_courses(): void
    {
        $admin = $this->makeAdmin();
        $course = $this->makeCourse();
        $other = Course::create(['name' => 'BSA', 'department' => 'Business']);
        $foreign = Subject::create(['course_id' => $other->id, 'name' => 'Foreign', 'code' => 'BA101', 'year_level' => '1st']);

        $res = $this->actingAs($admin, 'sanctum')->post(
            "/api/courses/{$course->id}/subjects/bulk-delete",
            ['ids' => [$foreign->id]]
        );

        $res->assertOk();
        $this->assertSame(0, $res->json('deleted'));
        $this->assertDatabaseHas('subjects', ['id' => $foreign->id]);
    }

    public function test_bulk_delete_requires_ids_and_permission(): void
    {
        $admin = $this->makeAdmin();
        $course = $this->makeCourse();

        $this->actingAs($admin, 'sanctum')->postJson(
            "/api/courses/{$course->id}/subjects/bulk-delete",
            ['ids' => []]
        )->assertStatus(422);

        $user = User::create([
            'firstname' => 'Test',
            'lastname' => 'Faculty',
            'email' => 'faculty2@test.com',
            'password' => 'password',
            'role' => 'faculty',
            'is_active' => true,
        ]);

        $this->actingAs($user, 'sanctum')->post(
            "/api/courses/{$course->id}/subjects/bulk-delete",
            ['ids' => ['00000000-0000-0000-0000-000000000000']]
        )->assertForbidden();
    }
}
