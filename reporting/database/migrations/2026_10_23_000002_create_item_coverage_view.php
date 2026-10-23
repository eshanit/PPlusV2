<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Competency coverage: one row per journey and tool item, showing whether the
 * item has been evaluated and how long ago.
 *
 * Mentors evaluate only what they observe, so an item can go unevaluated for a
 * long time. A journey cannot reach competency until every required item has a
 * latest score of 4 or 5, so a never-evaluated item silently blocks it.
 *
 * coverage_status:
 *   achieved   latest score is 4 or 5
 *   never      no score at all in the journey
 *   only_na    rated only N/A so far
 *   pending    evaluated, latest score below 4 (see sessions_since_evaluated
 *              for "not evaluated recently")
 *
 * Session order matches v_evaluation_group_status (date, round of day, created).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

        DB::statement(<<<'SQL'
            CREATE OR REPLACE VIEW v_item_coverage AS
            WITH ordered_sessions AS (
                SELECT
                    es.id,
                    es.evaluation_group_id,
                    es.mentee_id,
                    es.tool_id,
                    es.district_id,
                    es.facility_id,
                    es.evaluator_id,
                    es.eval_date,
                    ROW_NUMBER() OVER (
                        PARTITION BY es.evaluation_group_id
                        ORDER BY es.eval_date, COALESCE(es.day_round, 999999), es.created_at
                    ) AS session_number,
                    ROW_NUMBER() OVER (
                        PARTITION BY es.evaluation_group_id
                        ORDER BY es.eval_date DESC, COALESCE(es.day_round, 999999) DESC, es.created_at DESC
                    ) AS latest_rank
                FROM evaluation_sessions es
            ),
            journeys AS (
                SELECT
                    evaluation_group_id,
                    MAX(mentee_id) AS mentee_id,
                    MAX(tool_id) AS tool_id,
                    MAX(CASE WHEN latest_rank = 1 THEN district_id END) AS district_id,
                    MAX(CASE WHEN latest_rank = 1 THEN facility_id END) AS facility_id,
                    MAX(CASE WHEN latest_rank = 1 THEN evaluator_id END) AS last_evaluator_id,
                    MAX(eval_date) AS last_session_date,
                    COUNT(*) AS total_sessions
                FROM ordered_sessions
                GROUP BY evaluation_group_id
            ),
            item_scores AS (
                SELECT
                    os.evaluation_group_id,
                    sis.item_id,
                    os.session_number,
                    os.eval_date,
                    sis.mentee_score
                FROM v_session_item_scores sis
                JOIN ordered_sessions os ON os.id = sis.session_id
            ),
            item_stats AS (
                SELECT
                    evaluation_group_id,
                    item_id,
                    SUM(CASE WHEN mentee_score IS NOT NULL THEN 1 ELSE 0 END) AS times_evaluated,
                    SUM(CASE WHEN mentee_score IS NULL THEN 1 ELSE 0 END) AS times_na,
                    MAX(CASE WHEN mentee_score IS NOT NULL THEN session_number END) AS last_evaluated_session,
                    MAX(CASE WHEN mentee_score IS NOT NULL THEN eval_date END) AS last_evaluated_date
                FROM item_scores
                GROUP BY evaluation_group_id, item_id
            ),
            latest_scores AS (
                SELECT evaluation_group_id, item_id, mentee_score AS latest_score
                FROM (
                    SELECT
                        evaluation_group_id,
                        item_id,
                        mentee_score,
                        ROW_NUMBER() OVER (
                            PARTITION BY evaluation_group_id, item_id
                            ORDER BY session_number DESC
                        ) AS rn
                    FROM item_scores
                    WHERE mentee_score IS NOT NULL
                ) ranked
                WHERE rn = 1
            )
            SELECT
                j.evaluation_group_id,
                j.mentee_id,
                j.tool_id,
                j.district_id,
                j.facility_id,
                j.last_evaluator_id,
                j.last_session_date,
                j.total_sessions,
                ei.id AS item_id,
                ei.number AS item_number,
                ei.title AS item_title,
                ei.is_advanced,
                ei.sort_order,
                COALESCE(st.times_evaluated, 0) AS times_evaluated,
                COALESCE(st.times_na, 0) AS times_na,
                ls.latest_score,
                st.last_evaluated_session,
                st.last_evaluated_date,
                CASE
                    WHEN st.last_evaluated_session IS NULL THEN j.total_sessions
                    ELSE j.total_sessions - st.last_evaluated_session
                END AS sessions_since_evaluated,
                CASE
                    WHEN ls.latest_score >= 4 THEN 'achieved'
                    WHEN ls.latest_score IS NOT NULL THEN 'pending'
                    WHEN COALESCE(st.times_na, 0) > 0 THEN 'only_na'
                    ELSE 'never'
                END AS coverage_status
            FROM journeys j
            JOIN evaluation_items ei ON ei.tool_id = j.tool_id
            LEFT JOIN item_stats st ON st.evaluation_group_id = j.evaluation_group_id AND st.item_id = ei.id
            LEFT JOIN latest_scores ls ON ls.evaluation_group_id = j.evaluation_group_id AND ls.item_id = ei.id
            SQL);
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

        DB::statement('DROP VIEW IF EXISTS v_item_coverage');
    }
};
