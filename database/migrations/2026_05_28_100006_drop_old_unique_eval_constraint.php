<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evaluations', function (Blueprint $table) {
            // Drop the legacy unique_eval constraint (student_id + faculty_id + semester + academic_year)
            // It is fully replaced by unique_evaluatee_per_student
            // (student_id + evaluatee_id + evaluatee_type + semester + academic_year)
            // which correctly supports both faculty and staff evaluatees.
            $table->dropUnique('unique_eval');
        });
    }

    public function down(): void
    {
        Schema::table('evaluations', function (Blueprint $table) {
            $table->unique(['student_id', 'faculty_id', 'semester', 'academic_year'], 'unique_eval');
        });
    }
};
