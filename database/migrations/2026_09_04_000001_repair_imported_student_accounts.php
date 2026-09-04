<?php

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('students') || !Schema::hasTable('roles') || !Schema::hasTable('model_has_roles')) {
            return;
        }

        DB::table('students')
            ->where(function ($query) {
                $query->whereNull('student_type')->orWhere('student_type', '');
            })
            ->update(['student_type' => 'regular']);

        $studentRoleIds = Role::where('name', 'Student')->pluck('id')->all();
        if (!$studentRoleIds) {
            return;
        }

        User::where('role', 'student')->each(function (User $user) use ($studentRoleIds) {
            $user->roles()->sync($studentRoleIds);
        });
    }

    public function down(): void
    {
        // Account repair is intentionally not reversed.
    }
};