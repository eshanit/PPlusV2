<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sessions are sometimes recorded on paper first and typed up later, out of
 * chronological order, so created_at can't be trusted to reflect the true
 * same-day round order. The monitoring app now lets the evaluator pick an
 * explicit round for the day (ISession.roundOfDay); this column carries that
 * choice into MySQL so v_sessions_numbered.day_round_number orders by it
 * (falling back to created_at for older synced rows that predate the field).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evaluation_sessions', function (Blueprint $table) {
            $table->unsignedSmallInteger('day_round')->nullable()->after('phase');
        });

        DB::statement('
            CREATE OR REPLACE VIEW v_sessions_numbered AS
            SELECT
                s.*,
                ROW_NUMBER() OVER (
                    PARTITION BY s.evaluation_group_id
                    ORDER BY     s.eval_date, s.created_at
                ) AS session_number,
                ROW_NUMBER() OVER (
                    PARTITION BY s.evaluation_group_id, s.eval_date
                    ORDER BY     COALESCE(s.day_round, 999999), s.created_at
                ) AS day_round_number,
                COUNT(*) OVER (
                    PARTITION BY s.evaluation_group_id, s.eval_date
                ) AS day_round_count
            FROM evaluation_sessions s
        ');
    }

    public function down(): void
    {
        DB::statement('
            CREATE OR REPLACE VIEW v_sessions_numbered AS
            SELECT
                s.*,
                ROW_NUMBER() OVER (
                    PARTITION BY s.evaluation_group_id
                    ORDER BY     s.eval_date, s.created_at
                ) AS session_number,
                ROW_NUMBER() OVER (
                    PARTITION BY s.evaluation_group_id, s.eval_date
                    ORDER BY     s.created_at
                ) AS day_round_number,
                COUNT(*) OVER (
                    PARTITION BY s.evaluation_group_id, s.eval_date
                ) AS day_round_count
            FROM evaluation_sessions s
        ');

        Schema::table('evaluation_sessions', function (Blueprint $table) {
            $table->dropColumn('day_round');
        });
    }
};
