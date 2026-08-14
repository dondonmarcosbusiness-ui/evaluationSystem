<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('office_questions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('category_id')->constrained('office_categories')->onDelete('cascade');
            $table->text('question_text');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('office_questions');
    }
};
