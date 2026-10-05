<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditDedupeTest extends TestCase
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

    private function syncCount(string $action, User $actor): int
    {
        return AuditLog::where('action', $action)->where('user_id', $actor->id)->count();
    }

    public function test_resaving_identical_user_permissions_writes_one_audit_row(): void
    {
        $admin = $this->grant($this->makeUser('admin', 'dedupe-admin@test.com'), ['permission.manage']);
        $target = $this->makeUser('staff', 'dedupe-target@test.com');
        Permission::firstOrCreate(['name' => 'faculty.view', 'guard_name' => 'web']);

        $payload = ['permissions' => ['faculty.view']];

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/users/{$target->id}/permissions", $payload)
            ->assertStatus(200);

        // Identical save again → no changes → no second row.
        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/users/{$target->id}/permissions", $payload)
            ->assertStatus(200);

        $this->assertSame(1, $this->syncCount('user.permissions.synced', $admin));
    }

    public function test_a_real_permission_change_writes_a_new_audit_row(): void
    {
        $admin = $this->grant($this->makeUser('admin', 'dedupe-admin2@test.com'), ['permission.manage']);
        $target = $this->makeUser('staff', 'dedupe-target2@test.com');
        Permission::firstOrCreate(['name' => 'faculty.view', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'faculty.edit', 'guard_name' => 'web']);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/users/{$target->id}/permissions", ['permissions' => ['faculty.view']])
            ->assertStatus(200);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/users/{$target->id}/permissions", ['permissions' => ['faculty.view', 'faculty.edit']])
            ->assertStatus(200);

        $this->assertSame(2, $this->syncCount('user.permissions.synced', $admin));
    }

    public function test_resaving_identical_role_permissions_writes_one_audit_row(): void
    {
        $admin = $this->grant($this->makeUser('admin', 'dedupe-admin3@test.com'), ['permission.manage']);
        $permission = Permission::firstOrCreate(['name' => 'faculty.view', 'guard_name' => 'web']);
        $role = Role::create(['name' => 'Dedupe Role', 'guard_name' => 'web']);

        $payload = ['permissions' => [$permission->id]];

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/roles/{$role->id}/permissions", $payload)
            ->assertStatus(200);

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/roles/{$role->id}/permissions", $payload)
            ->assertStatus(200);

        $this->assertSame(1, $this->syncCount('role.permissions.synced', $admin));
    }

    public function test_repeated_identical_denials_are_logged_once(): void
    {
        $user = $this->makeUser('staff', 'dedupe-staff@test.com');

        $this->actingAs($user, 'sanctum')->getJson('/api/faculty')->assertStatus(403);
        $this->actingAs($user, 'sanctum')->getJson('/api/faculty')->assertStatus(403);

        $this->assertSame(1, $this->syncCount('permission.denied', $user));
    }
}
