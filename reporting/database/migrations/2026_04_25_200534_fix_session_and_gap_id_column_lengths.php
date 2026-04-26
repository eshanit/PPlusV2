<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop FK that references evaluation_sessions.id before altering it
        Schema::table('session_item_scores', function ($table) {
            $table->dropForeign(['session_id']);
        });

        // PouchDB session IDs are "session::{evaluationGroupId}::{timestamp}",
        // which can exceed 100 chars — char(36) silently truncates them.
        DB::statement('ALTER TABLE evaluation_sessions MODIFY id VARCHAR(200) NOT NULL');

        // Same pattern for gap IDs: "gap::{evaluationGroupId}::{timestamp}"
        DB::statement('ALTER TABLE gap_entries MODIFY id VARCHAR(200) NOT NULL');

        // FK column must match the referenced PK type
        DB::statement('ALTER TABLE session_item_scores MODIFY session_id VARCHAR(200) NOT NULL');

        Schema::table('session_item_scores', function ($table) {
            $table->foreign('session_id')->references('id')->on('evaluation_sessions')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('session_item_scores', function ($table) {
            $table->dropForeign(['session_id']);
        });

        DB::statement('ALTER TABLE session_item_scores MODIFY session_id CHAR(36) NOT NULL');
        DB::statement('ALTER TABLE evaluation_sessions MODIFY id CHAR(36) NOT NULL');
        DB::statement('ALTER TABLE gap_entries MODIFY id CHAR(36) NOT NULL');

        Schema::table('session_item_scores', function ($table) {
            $table->foreign('session_id')->references('id')->on('evaluation_sessions')->onDelete('cascade');
        });
    }
};
