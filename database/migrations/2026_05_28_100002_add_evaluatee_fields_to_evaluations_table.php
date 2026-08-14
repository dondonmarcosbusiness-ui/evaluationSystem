<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evaluations', function (Blueprint $table) {
            $table->enum('evaluatee_type', ['faculty'])->default('faculty')->after('ai_analysis');
            $table->uuid('evaluatee_id')->nullable()->after('evaluatee_type');
            $table->index(['evaluatee_id', 'evaluatee_type']);

            // Update unique constraint to include evaluatee_type
            $table->unique(['student_id', 'evaluatee_id', 'evaluatee_type', 'semester', 'academic_year'], 'unique_evaluatee_per_student');
        });
    }

    public function down(): void
    {
        Schema::table('evaluations', function (Blueprint $table) {
            $table->dropUnique('unique_evaluatee_per_student');
            $table->dropIndex(['evaluatee_id', 'evaluatee_type']);
            $table->dropColumn(['evaluatee_id', 'evaluatee_type']);
        });
    }
};
