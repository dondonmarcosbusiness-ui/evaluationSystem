<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('office_feedback', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('office_id')->constrained('offices')->onDelete('cascade');
            $table->foreignUuid('student_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('visitor_type'); // student, parent, faculty, alumni, visitor, others
            $table->string('visitor_name')->nullable();
            $table->string('student_number')->nullable();
            $table->string('contact_number')->nullable();
            $table->string('purpose_of_visit')->nullable();
            $table->integer('courtesy_rating')->nullable();
            $table->integer('professionalism_rating')->nullable();
            $table->integer('responsiveness_rating')->nullable();
            $table->integer('cleanliness_rating')->nullable();
            $table->integer('overall_rating')->nullable();
            $table->text('comments')->nullable();
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->string('device_type')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('office_feedback');
    }
};
