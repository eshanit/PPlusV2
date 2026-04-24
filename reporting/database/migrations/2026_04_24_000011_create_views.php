<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            CREATE OR REPLACE VIEW v_sessions_numbered AS
            SELECT
                s.*,
                ROW_NUMBER() OVER (
                    PARTITION BY s.evaluation_group_id
                    ORDER BY     s.eval_date, s.created_at
                ) AS session_number
            FROM evaluation_sessions s
        ");

        DB::statement("
            CREATE OR REPLACE VIEW v_session_averages AS
            SELECT
                sis.session_id,
                es.mentee_id,
                es.evaluator_id,
                es.tool_id,
                es.eval_date,
                es.district_id,
                es.facility_id,
                es.phase,
                ROUND(AVG(sis.mentee_score), 2) AS avg_mentee_score,
                COUNT(sis.id)                   AS scored_items,
                SUM(CASE WHEN sis.mentee_score IS NULL THEN 1 ELSE 0 END) AS na_items
            FROM session_item_scores sis
            JOIN evaluation_sessions es ON es.id = sis.session_id
            JOIN evaluation_items    ei ON ei.id = sis.item_id
            JOIN tools               t  ON t.id  = ei.tool_id AND t.slug != 'counselling'
            GROUP BY
                sis.session_id, es.mentee_id, es.evaluator_id,
                es.tool_id, es.eval_date, es.district_id,
                es.facility_id, es.phase
        ");
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS v_session_averages');
        DB::statement('DROP VIEW IF EXISTS v_sessions_numbered');
    }
};
