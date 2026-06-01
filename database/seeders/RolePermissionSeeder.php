<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;
use App\Models\Permission;
use App\Models\User;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        // Create Permissions
        $permissions = [
            'manage_rbac',
            'manage_users',
            'manage_faculty',
            'manage_courses',
            'manage_categories',
            'manage_questions',
            'view_dashboard',
            'view_reports',
            'give_evaluations',
            'view_evaluations',
        ];

        $guards = ['web', 'sanctum'];

        foreach ($guards as $guard) {
            foreach ($permissions as $permission) {
                Permission::firstOrCreate([
                    'name' => $permission,
                    'guard_name' => $guard
                ]);
            }

            // Create Roles and Assign Permissions
            
            // Admin (Assign all permissions EXCEPT give_evaluations)
            $adminRole = Role::firstOrCreate(['name' => 'Admin', 'guard_name' => $guard]);
            $adminRole->syncPermissions(Permission::where('guard_name', $guard)
                ->where('name', '!=', 'give_evaluations')
                ->get());

            // Faculty
            $facultyRole = Role::firstOrCreate(['name' => 'Faculty', 'guard_name' => $guard]);
            $facultyRole->syncPermissions(Permission::whereIn('name', [
                'view_evaluations',
            ])->where('guard_name', $guard)->get());

            // Student
            $studentRole = Role::firstOrCreate(['name' => 'Student', 'guard_name' => $guard]);
            $studentRole->syncPermissions(Permission::whereIn('name', [
                'give_evaluations',
            ])->where('guard_name', $guard)->get());
        }

        // Sync all existing users to their respective Spatie roles
        $users = User::all();
        $adminRoleIds = Role::where('name', 'Admin')->pluck('id')->toArray();
        $facultyRoleIds = Role::where('name', 'Faculty')->pluck('id')->toArray();
        $studentRoleIds = Role::where('name', 'Student')->pluck('id')->toArray();

        foreach ($users as $user) {
            if ($user->role === 'admin') {
                $user->roles()->sync($adminRoleIds);
                $user->permissions()->detach(); // Admins should only have role permissions
            } elseif ($user->role === 'faculty') {
                $user->roles()->sync($facultyRoleIds);
            } elseif ($user->role === 'student') {
                $user->roles()->sync($studentRoleIds);
            }
        }
    }
}
