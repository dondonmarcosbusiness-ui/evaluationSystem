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
        if (Schema::hasTable('login_logs')) {
            return;
        }

        Schema::create('login_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            // What the user typed (email or id number) — never a password/secret.
            $table->string('login_identifier', 190);
            // success | failed | locked | inactive
            $table->string('status', 20);
            // invalid_credentials | rate_limited | account_inactive | domain_rejected
            $table->string('reason', 40)->nullable();
            $table->string('driver', 20)->default('password');
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamp('created_at')->index();

            $table->index(['status', 'created_at']);
            $table->index('user_id', 'login_logs_user_id_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_logs');
    }
};
