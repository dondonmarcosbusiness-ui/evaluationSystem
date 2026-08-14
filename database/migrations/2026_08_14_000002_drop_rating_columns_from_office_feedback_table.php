<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('office_feedback', function (Blueprint $table) {
            $table->dropColumn([
                'courtesy_rating',
                'professionalism_rating',
                'responsiveness_rating',
                'cleanliness_rating',
                'overall_rating',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('office_feedback', function (Blueprint $table) {
            $table->integer('courtesy_rating')->nullable();
            $table->integer('professionalism_rating')->nullable();
            $table->integer('responsiveness_rating')->nullable();
            $table->integer('cleanliness_rating')->nullable();
            $table->integer('overall_rating')->nullable();
        });
    }
};
