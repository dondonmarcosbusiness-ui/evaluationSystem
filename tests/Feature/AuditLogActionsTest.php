<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogActionsTest extends TestCase
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

    public function test_actions_endpoint_requires_permission_manage(): void
    {
        $user = $this->makeUser('student', uniqid('student-') . '@test.com');

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/audit-logs/actions')
            ->assertStatus(403);
    }

    public function test_actions_endpoint_returns_distinct_sorted_actions(): void
    {
        $user = $this->makeUser('admin', uniqid('admin-') . '@test.com');
        $this->grant($user, ['permission.manage']);

        foreach (['role.created', 'permission.denied', 'role.created', 'office.updated'] as $action) {
            AuditLog::create([
                'user_id' => $user->id,
                'action' => $action,
                'auditable_type' => null,
                'auditable_id' => null,
                'old_values' => [],
                'new_values' => [],
                'ip_address' => '127.0.0.1',
                'user_agent' => 'phpunit',
            ]);
        }

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/audit-logs/actions');

        $response->assertStatus(200);

        $actions = $response->json();
        $this->assertSame(['office.updated', 'permission.denied', 'role.created'], $actions);
    }
}
