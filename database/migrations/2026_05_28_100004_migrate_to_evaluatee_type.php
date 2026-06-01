<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Update existing evaluation_categories to have evaluatee_type='faculty'
        DB::table('evaluation_categories')
            ->whereNull('evaluatee_type')
            ->orWhere('evaluatee_type', '!=', 'faculty')
            ->update(['evaluatee_type' => 'faculty']);

        // Update existing evaluations to have evaluatee_type='faculty' and evaluatee_id=faculty_id
        DB::table('evaluations')
            ->whereNull('evaluatee_type')
            ->update([
                'evaluatee_type' => 'faculty',
                'evaluatee_id' => DB::raw('faculty_id'),
            ]);
    }

    public function down(): void
    {
        // Reset evaluatee_type to null and clear evaluatee_id for staff evaluations only
        // Keep faculty evaluations intact for recovery
        DB::table('evaluations')
            ->where('evaluatee_type', 'staff')
            ->update([
                'evaluatee_type' => 'faculty',
                'evaluatee_id' => null,
            ]);
    }
};
