<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gap_entries', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->string('evaluation_group_id', 200);
            $table->char('mentee_id', 36);
            $table->char('evaluator_id', 36);
            $table->unsignedInteger('tool_id');
            $table->date('identified_at');
            $table->text('description');
            $table->json('domains');
            $table->boolean('covered_in_mentorship')->nullable();
            $table->boolean('covering_later')->default(false);
            $table->string('timeline', 255)->nullable();
            $table->enum('supervision_level', ['intensive_mentorship', 'ongoing_mentorship', 'independent_practice'])->nullable();
            $table->text('resolution_note')->nullable();
            $table->date('resolved_at')->nullable();
            $table->string('couchdb_rev', 100)->nullable();
            $table->timestamp('synced_at')->nullable();
            // Timestamps come from CouchDB — nullable until synced
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->foreign('mentee_id')->references('id')->on('users');
            $table->foreign('evaluator_id')->references('id')->on('users');
            $table->foreign('tool_id')->references('id')->on('tools');

            $table->index('evaluation_group_id', 'idx_gaps_group');
            $table->index('mentee_id', 'idx_gaps_mentee');
            $table->index('resolved_at', 'idx_gaps_resolved');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gap_entries');
    }
};
