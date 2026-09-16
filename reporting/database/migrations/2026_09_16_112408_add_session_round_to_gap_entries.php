<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gaps stay journey-scoped (no session _id link, per the monitoring app's
 * design), but the mentor can now see which round of the most recent session
 * was active when a gap was logged (ISession/IGapEntry.sessionRound). This
 * column carries that annotation into MySQL for reporting.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gap_entries', function (Blueprint $table) {
            $table->unsignedSmallInteger('session_round')->nullable()->after('identified_at');
        });
    }

    public function down(): void
    {
        Schema::table('gap_entries', function (Blueprint $table) {
            $table->dropColumn('session_round');
        });
    }
};
