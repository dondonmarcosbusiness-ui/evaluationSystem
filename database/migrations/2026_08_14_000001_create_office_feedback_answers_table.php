<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('office_feedback_answers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('office_feedback_id')->constrained('office_feedback')->cascadeOnDelete();
            $table->foreignUuid('office_question_id')->constrained('office_questions')->cascadeOnDelete();
            $table->boolean('answer'); // true = Yes, false = No
            $table->timestamps();

            $table->unique(['office_feedback_id', 'office_question_id'], 'office_fb_answers_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('office_feedback_answers');
    }
};
