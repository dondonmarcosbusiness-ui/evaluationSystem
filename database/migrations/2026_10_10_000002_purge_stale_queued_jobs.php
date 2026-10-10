<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * No queue worker ever ran in production, so the jobs table accumulated a
     * backlog of student completion mails (and window notices) that were never
     * sent. nixpacks now starts queue:work, which would flush that backlog in
     * one burst — days late. Drop it exactly once, before the worker starts.
     *
     * Migrations run before the worker in the start command, and the backlog is
     * stale by definition, so this is deliberately not reversible.
     */
    public function up(): void
    {
        if (!Schema::hasTable('jobs')) {
            return;
        }

        DB::table('jobs')->delete();
    }

    public function down(): void
    {
        //
    }
};
