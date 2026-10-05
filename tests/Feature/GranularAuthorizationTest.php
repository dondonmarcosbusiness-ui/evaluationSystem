<?php

namespace Tests\Feature;

use App\Models\Answer;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Evaluation;
use App\Models\Faculty;
use App\Models\Permission;
use App\Models\Question;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GranularAuthorizationTest extends TestCase
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
    private function makeFaculty(string $email, string $department = 'CIT'): array
    {
        $user = $this->makeUser('faculty', $email);
        $faculty = Faculty::create([
            'user_id' => $user->id,
            'department' => $department,
            'course' => 'BSIT',
            'position' => 'Instructor',
        ]);

        return [$user, $faculty];
    }

    private function makeEvaluation(Faculty $faculty, int $rating): Evaluation
    {
        $student = $this->makeUser('student', uniqid('student-') . '@test.com');

        $category = Category::create([
            'category_name' => 'Teaching Skill ' . uniqid(),
            'weight' => 1,
            'evaluatee_type' => 'faculty',
        ]);
        $question = Question::create([
            'category_id' => $category->id,
            'question_text' => 'Rates clearly?',
        ]);

        $evaluation = Evaluation::create([
            'student_id' => $student->id,
            'faculty_id' => $faculty->id,
            'evaluatee_type' => 'faculty',
            'evaluatee_id' => $faculty->id,
            'semester' => '1st Semester',
            'academic_year' => '2025-2026',
            'subject_code' => 'IT-SIA01',
            'comments' => 'A comment',
        ]);
        Answer::create([
            'evaluation_id' => $evaluation->id,
            'question_id' => $question->id,
            'rating' => $rating,
        ]);

        return $evaluation;
    }

    public function test_route_requires_permission_grant_and_audits_denial(): void
    {
        $user = $this->makeUser('staff', 'staff@test.com');

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/faculty')
            ->assertStatus(403);

        $this->assertSame(
            1,
            AuditLog::where('action', 'permission.denied')
                ->where('user_id', $user->id)
                ->count()
        );

        $this->grant($user, ['faculty.view']);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/faculty')
            ->assertStatus(200);
    }

    public function test_audit_log_endpoint_requires_permission_manage(): void
    {
        $user = $this->makeUser('adminish', 'adminish@test.com');

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/audit-logs')
            ->assertStatus(403);

        $this->grant($user, ['permission.manage']);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/audit-logs')
            ->assertStatus(200);
    }

    public function test_results_own_scope_blocks_cross_record_access(): void
    {
        [$userA, $facultyA] = $this->makeFaculty('a@test.com');
        [, $facultyB] = $this->makeFaculty('b@test.com');
        $this->grant($userA, ['evaluation.view', 'evaluation.view.own']);

        $this->makeEvaluation($facultyA, 5);
        $this->makeEvaluation($facultyB, 1);

        $this->actingAs($userA, 'sanctum')
            ->getJson("/api/evaluations/results/{$facultyA->id}?semester=all&academic_year=all")
            ->assertStatus(200);

        $this->actingAs($userA, 'sanctum')
            ->getJson("/api/evaluations/results/{$facultyB->id}?semester=all&academic_year=all")
            ->assertStatus(403);
    }

    public function test_results_team_scope_limits_to_own_department(): void
    {
        [$userA, $facultyA] = $this->makeFaculty('team-a@test.com', 'CIT');
        [, $facultySameDept] = $this->makeFaculty('team-b@test.com', 'CIT');
        [, $facultyOtherDept] = $this->makeFaculty('team-c@test.com', 'COE');
        $this->grant($userA, ['evaluation.view', 'evaluation.view.team']);

        $this->makeEvaluation($facultyA, 5);
        $this->makeEvaluation($facultySameDept, 4);
        $this->makeEvaluation($facultyOtherDept, 2);

        $this->actingAs($userA, 'sanctum')
            ->getJson("/api/evaluations/results/{$facultySameDept->id}?semester=all&academic_year=all")
            ->assertStatus(200);

        $this->actingAs($userA, 'sanctum')
            ->getJson("/api/evaluations/results/{$facultyOtherDept->id}?semester=all&academic_year=all")
            ->assertStatus(403);

        // "all evaluatees" collapses to the caller's department.
        $this->actingAs($userA, 'sanctum')
            ->getJson('/api/evaluations/results/all?semester=all&academic_year=all')
            ->assertStatus(200);
    }

    public function test_results_all_scope_allows_cross_record_access(): void
    {
        $admin = $this->makeUser('admin', 'admin-scope@test.com');
        [, $facultyA] = $this->makeFaculty('scope-a@test.com');
        [, $facultyB] = $this->makeFaculty('scope-b@test.com');
        $this->grant($admin, ['evaluation.view', 'evaluation.view.all']);

        $this->makeEvaluation($facultyA, 5);
        $this->makeEvaluation($facultyB, 1);

        $this->actingAs($admin, 'sanctum')
            ->getJson("/api/evaluations/results/{$facultyB->id}?semester=all&academic_year=all")
            ->assertStatus(200);
    }

    public function test_missing_base_scope_permission_denies_results_even_with_variant(): void
    {
        $user = $this->makeUser('faculty', 'variant@test.com');
        [, $faculty] = $this->makeFaculty('variant-f@test.com');
        // Holds only the scope variant, never the base capability.
        $this->grant($user, ['evaluation.view.all']);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/evaluations/results/{$faculty->id}?semester=all&academic_year=all")
            ->assertStatus(403);
    }

    public function test_cannot_modify_own_role_permissions_or_role(): void
    {
        $admin = $this->makeUser('admin', 'own-role@test.com');
        $this->grant($admin, ['permission.manage', 'role.edit', 'role.delete', 'role.view']);

        $ownRole = Role::create(['name' => 'Admin', 'guard_name' => 'web']);
        $otherRole = Role::create(['name' => 'Reviewer', 'guard_name' => 'web']);
        $admin->assignRole($ownRole);

        $permissionId = Permission::firstOrCreate(
            ['name' => 'faculty.view', 'guard_name' => 'web']
        )->id;

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/roles/{$ownRole->id}/permissions", ['permissions' => [$permissionId]])
            ->assertStatus(403);

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/roles/{$ownRole->id}", ['name' => 'Renamed'])
            ->assertStatus(403);

        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/roles/{$ownRole->id}")
            ->assertStatus(403);

        // A different role is fair game.
        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/roles/{$otherRole->id}/permissions", ['permissions' => [$permissionId]])
            ->assertStatus(200);

        $this->assertTrue($otherRole->fresh()->permissions->contains('name', 'faculty.view'));
    }

    public function test_user_cannot_modify_own_direct_permissions(): void
    {
        $user = $this->makeUser('admin', 'self-perms@test.com');
        $this->grant($user, ['permission.manage']);

        $other = $this->makeUser('faculty', 'other-perms@test.com');

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/users/{$user->id}/permissions", ['permissions' => []])
            ->assertStatus(403);

        // Another user's direct permissions can be managed.
        $this->actingAs($user, 'sanctum')
            ->postJson("/api/users/{$other->id}/permissions", ['permissions' => []])
            ->assertStatus(200);
    }

    public function test_catalog_permission_cannot_be_deleted(): void
    {
        $user = $this->makeUser('admin', 'perm-delete@test.com');
        $this->grant($user, ['permission.manage']);

        $permission = Permission::firstOrCreate(
            ['name' => 'faculty.view', 'guard_name' => 'web']
        );

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/permissions/{$permission->id}")
            ->assertStatus(403);

        $permission = Permission::firstOrCreate(
            ['name' => 'my.custom.thing', 'guard_name' => 'web']
        );

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/permissions/{$permission->id}")
            ->assertStatus(200);
    }

    public function test_role_permission_sync_validates_permission_ids(): void
    {
        $user = $this->makeUser('admin', 'perm-ids@test.com');
        $this->grant($user, ['permission.manage']);
        $role = Role::create(['name' => 'Reviewer2', 'guard_name' => 'web']);

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/roles/{$role->id}/permissions", ['permissions' => ['not-a-uuid']])
            ->assertStatus(422);
    }

    /**
     * Field bug: a grant saved through the matrix took no effect until the
     * Spatie cache expired. Pivot syncs do not flush the registrar cache, so
     * a warm cache (primed by an earlier request) kept enforcing the old
     * grants and the middleware 403'd fresh, valid permissions.
     */
    public function test_role_permission_grant_takes_effect_immediately_with_warm_cache(): void
    {
        $admin = $this->makeUser('admin', 'cache-admin@test.com');
        $this->grant($admin, ['permission.manage']);

        $role = Role::create(['name' => 'CacheRole', 'guard_name' => 'web']);
        $member = $this->makeUser('staff', 'cache-member@test.com');
        $member->assignRole($role);

        Permission::firstOrCreate(['name' => 'faculty.view', 'guard_name' => 'web']);

        // Prime the registrar cache with the ungranted state — this is what
        // every subsequent middleware run would read in production.
        $this->actingAs($member, 'sanctum')
            ->getJson('/api/faculty')
            ->assertStatus(403);

        // Grant through the real endpoint (pivot sync, no explicit flush).
        $permissionId = Permission::where('name', 'faculty.view')->value('id');

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/roles/{$role->id}/permissions", ['permissions' => [$permissionId]])
            ->assertStatus(200);

        // The same warm cache process must allow immediately — no expiry wait.
        $this->actingAs($member, 'sanctum')
            ->getJson('/api/faculty')
            ->assertStatus(200);
    }
}
