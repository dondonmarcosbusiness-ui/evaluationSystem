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
        $columns = [
            'subjects'           => 'code',
            'sections'           => 'name',
            'faculty_assignments'=> 'semester',
            'students'           => 'section_id',
            'enrollments'        => 'academic_year',
        ];

        foreach ($columns as $table => $after) {
            if (!Schema::hasColumn($table, 'year_level')) {
                Schema::table($table, function (Blueprint $table) use ($after) {
                    $table->string('year_level', 20)->nullable()->after($after);
                });
            }
        }
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
