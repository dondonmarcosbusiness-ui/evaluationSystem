<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A restored backup can revert the migrations table while the table
        // itself survives (it is newer than the dump), so guard against
        // re-creating it.
        if (Schema::hasTable('student_online_peaks')) {
            return;
        }

        Schema::create('student_online_peaks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // One row per calendar day (app timezone) — the high-water mark
            // of concurrently online students observed that day.
            $table->date('date')->unique();
            $table->unsignedSmallInteger('peak')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_online_peaks');
    }
};
