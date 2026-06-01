<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('evaluation_categories', function (Blueprint $table) {
            $table->string('academic_year')->nullable()->after('weight');
            $table->string('semester')->nullable()->after('academic_year');
        });

        // Set current settings for existing records so they don't disappear
        try {
            $settings = DB::table('settings')->get()->pluck('value', 'key');
            $activeSemester = isset($settings['active_semester']) ? json_decode($settings['active_semester'], true) : null;
            $activeAcademicYear = isset($settings['active_academic_year']) ? json_decode($settings['active_academic_year'], true) : null;

            // In our system, settings are stored as JSON strings because of the 'array' cast in the model
            // But sometimes they might be raw strings if inserted differently.
            // Let's try to handle both or just use the raw value if decode fails.
            
            $activeSemester = is_string($activeSemester) ? $activeSemester : (isset($settings['active_semester']) ? trim($settings['active_semester'], '"') : null);
            $activeAcademicYear = is_string($activeAcademicYear) ? $activeAcademicYear : (isset($settings['active_academic_year']) ? trim($settings['active_academic_year'], '"') : null);

            if ($activeSemester || $activeAcademicYear) {
                DB::table('evaluation_categories')->update([
                    'semester' => $activeSemester,
                    'academic_year' => $activeAcademicYear
                ]);
            }
        } catch (\Exception $e) {
            // Log or ignore if settings table doesn't exist or other issues
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('evaluation_categories', function (Blueprint $table) {
            $table->dropColumn(['academic_year', 'semester']);
        });
    }
};
