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
        Schema::table('evaluations', function (Blueprint $table) {
            $table->string('subject_code')->nullable()->after('academic_year');
            $table->string('year_section')->nullable()->after('subject_code');
            $table->text('comments')->nullable()->after('year_section');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('evaluations', function (Blueprint $table) {
            $table->dropColumn(['subject_code', 'year_section', 'comments']);
        });
    }
};
