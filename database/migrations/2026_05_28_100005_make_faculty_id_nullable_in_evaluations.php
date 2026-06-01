<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evaluations', function (Blueprint $table) {
            // Drop the foreign key constraint first, then re-add as nullable
            $table->dropForeign(['faculty_id']);
            $table->uuid('faculty_id')->nullable()->change();
            $table->foreign('faculty_id')->references('id')->on('faculty')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('evaluations', function (Blueprint $table) {
            $table->dropForeign(['faculty_id']);
            $table->uuid('faculty_id')->nullable(false)->change();
            $table->foreign('faculty_id')->references('id')->on('faculty')->onDelete('cascade');
        });
    }
};
