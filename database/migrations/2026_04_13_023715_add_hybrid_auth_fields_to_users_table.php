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
            $table->string('id_number')->unique()->after('id')->nullable();
            $table->string('google_id')->unique()->after('password')->nullable();
            $table->string('google_email')->after('google_id')->nullable();
            $table->boolean('is_google_linked')->default(false)->after('google_email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['id_number', 'google_id', 'google_email', 'is_google_linked']);
        });
    }
};
