<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enrollments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('student_id')->constrained('users')->onDelete('cascade');
            $table->foreignUuid('subject_id')->constrained('subjects')->onDelete('cascade');
            $table->foreignUuid('instructor_id')->constrained('faculty')->onDelete('cascade');
            $table->string('semester');
            $table->string('academic_year');
            $table->timestamps();

            // Prevent duplicate enrollment for the same student/subject/instructor in a semester
            $table->unique(['student_id', 'subject_id', 'instructor_id', 'semester', 'academic_year'], 'unique_enrollment');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollments');
    }
};
