<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('evaluations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('student_id')->constrained('users')->onDelete('cascade');
            $table->foreignUuid('faculty_id')->constrained('faculty')->onDelete('cascade');
            $table->string('semester'); // 1st, 2nd, Summer
            $table->string('academic_year'); // e.g., 2025-2026
            $table->timestamp('submitted_at')->useCurrent();
            $table->timestamps();
            
            // Prevent duplicate evaluations by the same student for the same faculty/semester/year
            $table->unique(['student_id', 'faculty_id', 'semester', 'academic_year'], 'unique_eval');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('evaluations');
    }
};
