<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RoleController extends Controller
{
    public function index()
    {
        return response()->json(Role::where('guard_name', 'web')->with('permissions')->get());
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|unique:roles,name',
            'guard_name' => 'nullable|string',
            'permissions' => 'nullable|array'
        ]);

        $role = Role::create([
            'name' => $request->name,
            'guard_name' => $request->guard_name ?? 'web'
        ]);

        if ($request->has('permissions')) {
            $role->syncPermissions($request->permissions);
        }

        return response()->json([
            'message' => 'Role created successfully',
            'role' => $role->load('permissions')
        ], 201);
    }

    public function show(Role $role)
    {
        return response()->json($role->load('permissions'));
    }

    public function update(Request $request, Role $role)
    {
        $request->validate([
            'name' => 'required|unique:roles,name,' . $role->id,
            'permissions' => 'nullable|array'
        ]);

        $role->update(['name' => $request->name]);

        if ($request->has('permissions')) {
            $role->syncPermissions($request->permissions);
        }

        return response()->json([
            'message' => 'Role updated successfully',
            'role' => $role->load('permissions')
        ]);
    }

    public function destroy(Role $role)
    {
        $role->delete();
        return response()->json(['message' => 'Role deleted successfully']);
    }

    public function assignPermissions(Request $request, Role $role)
    {
        $request->validate([
            'permissions' => 'required|array'
        ]);

        $role->syncPermissions($request->permissions);

        return response()->json([
            'message' => 'Permissions synced successfully',
            'role' => $role->load('permissions')
        ]);
    }
}
