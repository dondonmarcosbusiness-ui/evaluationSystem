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
        Schema::create('students', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->onDelete('cascade');
            $table->string('course')->nullable();
            $table->string('section')->nullable();
            $table->uuid('section_id')->nullable();
            $table->timestamps();
            
            // We use standard unsignedBigInteger here instead of constrained for section_id 
            // since the original users table only had unsignedBigInteger without strict constraints
        });

        // Migrate existing students
        $users = \Illuminate\Support\Facades\DB::table('users')->where('role', 'student')->get();
        foreach ($users as $user) {
            \Illuminate\Support\Facades\DB::table('students')->insert([
                'user_id' => $user->id,
                'course' => $user->course,
                'section' => $user->section,
                'section_id' => $user->section_id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
