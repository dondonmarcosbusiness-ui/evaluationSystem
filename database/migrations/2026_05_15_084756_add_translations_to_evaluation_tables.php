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
        Schema::table('evaluation_questions', function (Blueprint $table) {
            $table->text('question_text_tl')->nullable()->after('question_text');
        });

        Schema::table('evaluation_categories', function (Blueprint $table) {
            $table->string('category_name_tl')->nullable()->after('category_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('evaluation_questions', function (Blueprint $table) {
            $table->dropColumn('question_text_tl');
        });

        Schema::table('evaluation_categories', function (Blueprint $table) {
            $table->dropColumn('category_name_tl');
        });
    }
};
