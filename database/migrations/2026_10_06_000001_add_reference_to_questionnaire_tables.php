<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evaluation_categories', function (Blueprint $table) {
            if (!Schema::hasColumn('evaluation_categories', 'reference')) {
                $table->string('reference')->nullable()->after('category_name_tl');
            }
        });

        Schema::table('evaluation_questions', function (Blueprint $table) {
            if (!Schema::hasColumn('evaluation_questions', 'reference')) {
                $table->string('reference')->nullable()->after('question_text_tl');
            }
        });
    }

    public function down(): void
    {
        Schema::table('evaluation_categories', function (Blueprint $table) {
            $table->dropColumn('reference');
        });

        Schema::table('evaluation_questions', function (Blueprint $table) {
            $table->dropColumn('reference');
        });
    }
};
