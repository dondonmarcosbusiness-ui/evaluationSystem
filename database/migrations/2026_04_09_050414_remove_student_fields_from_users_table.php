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
        Schema::table('users', function (Blueprint $table) {
            $columnsToDrop = [];
            if (Schema::hasColumn('users', 'course')) {
                $columnsToDrop[] = 'course';
            }
            if (Schema::hasColumn('users', 'section')) {
                $columnsToDrop[] = 'section';
            }
            if (Schema::hasColumn('users', 'section_id')) {
                // Drop foreign key if it exists. Easiest way in Laravel if we named it defaults:
                $table->dropForeign(['section_id']);
                $columnsToDrop[] = 'section_id';
            }
            
            if (!empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'course')) {
                $table->string('course')->nullable();
            }
            if (!Schema::hasColumn('users', 'section')) {
                $table->string('section')->nullable();
            }
            if (!Schema::hasColumn('users', 'section_id')) {
                $table->uuid('section_id')->nullable();
            }
        });
    }
};
