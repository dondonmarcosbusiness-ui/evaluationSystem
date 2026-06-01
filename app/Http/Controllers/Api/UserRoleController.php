<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class UserRoleController extends Controller
{
    public function assignRole(Request $request, User $user)
    {
        $request->validate([
            'roles' => 'nullable|array'
        ]);

        $user->syncRoles($request->roles ?? []);

        return response()->json([
            'message' => 'Roles assigned successfully',
            'user' => $user->load('roles', 'permissions')
        ]);
    }

    public function assignPermission(Request $request, User $user)
    {
        $request->validate([
            'permissions' => 'nullable|array'
        ]);

        $user->syncPermissions($request->permissions ?? []);

        return response()->json([
            'message' => 'Permissions assigned successfully',
            'user' => $user->load('roles', 'permissions')
        ]);
    }

    public function getUserPermissions(User $user)
    {
        return response()->json([
            'roles' => $user->getRoleNames(),
            'permissions' => $user->getAllPermissions()->pluck('name'),
            'direct_permissions' => $user->getPermissionNames()
        ]);
    }
}
