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
        Schema::create('faculty_assignments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('faculty_id')->constrained('faculty')->onDelete('cascade');
            $table->foreignUuid('subject_id')->constrained('subjects')->onDelete('cascade');
            $table->foreignUuid('section_id')->constrained('sections')->onDelete('cascade');
            $table->string('academic_year')->nullable();
            $table->string('semester')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('faculty_assignments');
    }
};
