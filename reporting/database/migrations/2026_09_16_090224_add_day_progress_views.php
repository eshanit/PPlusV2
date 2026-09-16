<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Adds a per-day "round" number to v_sessions_numbered (for mentors who run
 * several evaluation passes on the same mentee+tool on the same calendar day)
 * and a new v_day_progress view exposing two derived comparisons: the
 * within-day delta (first round vs. last round of a day) and the day-to-day
 * delta (last round of the previous day vs. first round of the current day).
 * Both numbers are computed, never stored, matching the existing
 * v_sessions_numbered.session_number pattern.
 */
return new class extends Migration
{
    public function up(): void
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

        DB::statement('
            CREATE OR REPLACE VIEW v_day_progress AS
            WITH day_rounds AS (
                SELECT
                    sn.evaluation_group_id,
                    sn.tool_id,
                    sn.mentee_id,
                    sn.district_id,
                    sn.facility_id,
                    sn.eval_date,
                    sn.day_round_number,
                    sn.day_round_count,
                    sa.avg_mentee_score
                FROM v_sessions_numbered sn
                LEFT JOIN v_session_averages sa ON sa.session_id = sn.id
            ),
            day_agg AS (
                SELECT
                    evaluation_group_id,
                    tool_id,
                    mentee_id,
                    district_id,
                    facility_id,
                    eval_date,
                    MAX(day_round_count) AS rounds_that_day,
                    ROUND(AVG(avg_mentee_score), 2) AS day_avg_score,
                    MAX(CASE WHEN day_round_number = 1 THEN avg_mentee_score END) AS first_round_avg,
                    MAX(CASE WHEN day_round_number = day_round_count THEN avg_mentee_score END) AS last_round_avg
                FROM day_rounds
                GROUP BY evaluation_group_id, tool_id, mentee_id, district_id, facility_id, eval_date
            )
            SELECT
                evaluation_group_id,
                tool_id,
                mentee_id,
                district_id,
                facility_id,
                eval_date,
                rounds_that_day,
                day_avg_score,
                first_round_avg,
                last_round_avg,
                ROUND(last_round_avg - first_round_avg, 2) AS intra_day_delta,
                LAG(eval_date) OVER (
                    PARTITION BY evaluation_group_id ORDER BY eval_date
                ) AS prev_eval_date,
                LAG(last_round_avg) OVER (
                    PARTITION BY evaluation_group_id ORDER BY eval_date
                ) AS prev_day_last_round_avg,
                ROUND(first_round_avg - LAG(last_round_avg) OVER (
                    PARTITION BY evaluation_group_id ORDER BY eval_date
                ), 2) AS day_over_day_delta
            FROM day_agg
        ');
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS v_day_progress');

        DB::statement('
            CREATE OR REPLACE VIEW v_sessions_numbered AS
            SELECT
                s.*,
                ROW_NUMBER() OVER (
                    PARTITION BY s.evaluation_group_id
                    ORDER BY     s.eval_date, s.created_at
                ) AS session_number
            FROM evaluation_sessions s
        ');
    }
};
