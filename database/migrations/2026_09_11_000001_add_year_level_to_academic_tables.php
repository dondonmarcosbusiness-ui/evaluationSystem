<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add nullable year_level to academic tables.
     * Nullable so existing untagged records are treated as "all years".
     */
    public function up(): void
    {
        Schema::table('subjects', function (Blueprint $table) {
            $table->string('year_level', 20)->nullable()->after('code');
        });

        Schema::table('sections', function (Blueprint $table) {
            $table->string('year_level', 20)->nullable()->after('name');
        });

        Schema::table('faculty_assignments', function (Blueprint $table) {
            $table->string('year_level', 20)->nullable()->after('semester');
        });

        Schema::table('students', function (Blueprint $table) {
            $table->string('year_level', 20)->nullable()->after('section_id');
        });

        Schema::table('enrollments', function (Blueprint $table) {
            $table->string('year_level', 20)->nullable()->after('academic_year');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->dropColumn('year_level');
        });

        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn('year_level');
        });

        Schema::table('faculty_assignments', function (Blueprint $table) {
            $table->dropColumn('year_level');
        });

        Schema::table('sections', function (Blueprint $table) {
            $table->dropColumn('year_level');
        });

        Schema::table('subjects', function (Blueprint $table) {
            $table->dropColumn('year_level');
        });
    }
};
