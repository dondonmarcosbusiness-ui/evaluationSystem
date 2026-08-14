<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evaluation_categories', function (Blueprint $table) {
            if (!Schema::hasColumn('evaluation_categories', 'evaluatee_type')) {
                $table->enum('evaluatee_type', ['faculty'])->default('faculty')->after('weight');
            }
            $table->index(['evaluatee_type', 'semester', 'academic_year'], 'eval_cat_type_sem_ay_index');
        });
    }

    public function down(): void
    {
        Schema::table('evaluation_categories', function (Blueprint $table) {
            $table->dropIndex('eval_cat_type_sem_ay_index');
            $table->dropColumn('evaluatee_type');
        });
    }
};
