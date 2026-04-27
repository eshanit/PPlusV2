<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('session_item_scores', function (Blueprint $table) {
            $table->id();
            $table->char('session_id', 36);
            $table->unsignedInteger('item_id');
            $table->unsignedTinyInteger('mentee_score')->nullable();

            $table->unique(['session_id', 'item_id'], 'uq_score_session_item');

            $table->foreign('session_id')->references('id')->on('evaluation_sessions')->onDelete('cascade');
            $table->foreign('item_id')->references('id')->on('evaluation_items');

            $table->index('session_id', 'idx_scores_session');
            $table->index('item_id', 'idx_scores_item');
        });

        // SQLite doesn't support CHECK constraints
        if (DB::connection()->getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE session_item_scores ADD CONSTRAINT chk_mentee_score CHECK (mentee_score BETWEEN 1 AND 5)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('session_item_scores');
    }
};
