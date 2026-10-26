<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Gap statuses (client request, 2026-10-26): open → in_progress ("being
 * addressed", with an optional target_date) → resolved, and a resolved gap can
 * be reopened. status_history keeps every change as JSON. Gaps synced before
 * this are resolved when resolved_at is set, otherwise open.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gap_entries', function (Blueprint $table) {
            $table->string('status', 20)->default('open')->after('supervision_level')->index();
            $table->date('target_date')->nullable()->after('status');
            $table->longText('status_history')->nullable()->after('resolved_at');
        });

        DB::table('gap_entries')->whereNotNull('resolved_at')->update(['status' => 'resolved']);
    }

    public function down(): void
    {
        Schema::table('gap_entries', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropColumn(['status', 'target_date', 'status_history']);
        });
    }
};
