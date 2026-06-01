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
        Schema::create('evaluation_categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('category_name');
            $table->decimal('weight', 5, 2); // e.g., 0.40 for 40%
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('evaluation_categories');
    }
};
