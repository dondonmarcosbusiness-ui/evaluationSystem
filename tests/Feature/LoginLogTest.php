<?php

namespace Tests\Feature;

use App\Models\LoginLog;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginLogTest extends TestCase
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

    private function makeUser(string $role, string $email, bool $active = true): User
    {
        return User::create([
            'firstname' => 'Test',
            'lastname' => ucfirst($role),
            'email' => $email,
            'password' => 'password',
            'role' => $role,
            'is_active' => $active,
        ]);
    }

    private function attempt(string $login, string $password = 'password')
    {
        return $this->postJson('/api/login', ['login' => $login, 'password' => $password]);
    }

    public function test_successful_login_is_recorded(): void
    {
        $user = $this->makeUser('staff', uniqid('ok-') . '@test.com');

        $this->attempt($user->email)->assertStatus(200);

        $log = LoginLog::first();
        $this->assertNotNull($log);
        $this->assertSame('success', $log->status);
        $this->assertSame($user->id, $log->user_id);
        $this->assertSame('password', $log->driver);
        $this->assertNull($log->reason);
        $this->assertNotNull($log->created_at);
    }

    public function test_wrong_password_is_recorded_as_failed(): void
    {
        $user = $this->makeUser('staff', uniqid('bad-') . '@test.com');

        $this->attempt($user->email, 'wrong-password')->assertStatus(401);

        $log = LoginLog::first();
        $this->assertSame('failed', $log->status);
        $this->assertSame('invalid_credentials', $log->reason);
        $this->assertSame($user->id, $log->user_id);
        $this->assertStringNotContainsString('wrong-password', json_encode($log->toArray()));
    }

    public function test_unknown_account_is_recorded_without_user(): void
    {
        $this->attempt(uniqid('ghost-') . '@test.com')->assertStatus(401);

        $log = LoginLog::first();
        $this->assertSame('failed', $log->status);
        $this->assertNull($log->user_id);
    }

    public function test_deactivated_account_is_recorded_as_inactive(): void
    {
        $user = $this->makeUser('staff', uniqid('off-') . '@test.com', false);

        $this->attempt($user->email)->assertStatus(403);

        $log = LoginLog::first();
        $this->assertSame('inactive', $log->status);
        $this->assertSame('account_inactive', $log->reason);
    }

    public function test_lockout_is_recorded(): void
    {
        $user = $this->makeUser('staff', uniqid('lock-') . '@test.com');

        for ($i = 0; $i < 5; $i++) {
            $this->attempt($user->email, 'nope-' . $i)->assertStatus(401);
        }

        $this->attempt($user->email, 'nope')->assertStatus(429);

        $this->assertSame(5, LoginLog::where('status', 'failed')->count());
        $this->assertSame(1, LoginLog::where('status', 'locked')->count());
    }

    public function test_login_log_endpoint_requires_permission_manage(): void
    {
        $user = $this->makeUser('staff', uniqid('view-') . '@test.com');

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/auth/login-logs')
            ->assertStatus(403);

        $this->grant($user, ['permission.manage']);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/auth/login-logs')
            ->assertStatus(200);
    }

    public function test_login_log_endpoint_returns_entries_and_summary(): void
    {
        $admin = $this->grant($this->makeUser('admin', uniqid('sum-') . '@test.com'), ['permission.manage']);
        $user = $this->makeUser('staff', uniqid('rows-') . '@test.com');

        $this->attempt($user->email)->assertStatus(200);
        $this->attempt($user->email, 'wrong')->assertStatus(401);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/auth/login-logs')
            ->assertStatus(200);

        $this->assertSame(2, $response->json('summary.total'));
        $this->assertSame(1, $response->json('summary.success'));
        $this->assertSame(1, $response->json('summary.failed'));
        $this->assertCount(2, $response->json('data'));

        $filtered = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/auth/login-logs?status=failed')
            ->assertStatus(200);

        $this->assertSame(1, $filtered->json('summary.failed'));
        $this->assertCount(1, $filtered->json('data'));
    }
}
