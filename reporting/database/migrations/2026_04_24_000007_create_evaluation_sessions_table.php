<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluation_sessions', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->string('evaluation_group_id', 200);
            $table->char('mentee_id', 36);
            $table->char('evaluator_id', 36);
            $table->unsignedInteger('tool_id');
            $table->date('eval_date');
            $table->char('facility_id', 36)->nullable();
            $table->char('district_id', 36)->nullable();
            $table->enum('phase', ['initial_intensive', 'ongoing', 'supervision'])->nullable();
            $table->text('notes')->nullable();
            $table->string('couchdb_rev', 100)->nullable();
            $table->timestamp('synced_at')->nullable();
            // Timestamps come from CouchDB — nullable until synced
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->foreign('mentee_id')->references('id')->on('users');
            $table->foreign('evaluator_id')->references('id')->on('users');
            $table->foreign('tool_id')->references('id')->on('tools');
            $table->foreign('facility_id')->references('id')->on('facilities');
            $table->foreign('district_id')->references('id')->on('districts');

            $table->index('evaluation_group_id', 'idx_sessions_group');
            $table->index('mentee_id', 'idx_sessions_mentee');
            $table->index('evaluator_id', 'idx_sessions_evaluator');
            $table->index('tool_id', 'idx_sessions_tool');
            $table->index('district_id', 'idx_sessions_district');
            $table->index('facility_id', 'idx_sessions_facility');
            $table->index('eval_date', 'idx_sessions_eval_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluation_sessions');
    }
};
