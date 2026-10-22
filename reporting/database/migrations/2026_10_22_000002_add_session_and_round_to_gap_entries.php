<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gaps logged from the session form record the session, and the round within
 * it, where they were found. round_index matches session_item_scores.round_index.
 * The gap still belongs to the journey (evaluation_group_id); this lets
 * reports show the gaps identified in each session.
 *
 * No foreign key: CouchDB can deliver a gap before its session, and gaps
 * logged from the journey page have no session.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('gap_entries', 'session_id')) {
            Schema::table('gap_entries', function (Blueprint $table) {
                $table->string('session_id', 200)->nullable()->after('evaluation_group_id');
                $table->index('session_id');
            });
        }

        if (! Schema::hasColumn('gap_entries', 'round_index')) {
            Schema::table('gap_entries', function (Blueprint $table) {
                $table->unsignedSmallInteger('round_index')->nullable()->after('session_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('gap_entries', 'round_index')) {
            Schema::table('gap_entries', function (Blueprint $table) {
                $table->dropColumn('round_index');
            });
        }

        if (Schema::hasColumn('gap_entries', 'session_id')) {
            Schema::table('gap_entries', function (Blueprint $table) {
                $table->dropIndex(['session_id']);
                $table->dropColumn('session_id');
            });
        }
    }
};
