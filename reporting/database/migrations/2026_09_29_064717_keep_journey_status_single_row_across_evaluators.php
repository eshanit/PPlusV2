<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Keeps each journey (mentee + tool) as a single row in v_evaluation_group_status.
 *
 * The view previously grouped by evaluator_id, district_id and facility_id as
 * well as evaluation_group_id, so a journey with sessions by more than one
 * mentor — or at more than one facility — split into several rows, each with
 * its own session count and competency status. It now groups by the journey
 * only, and reports evaluator/district/facility from the latest session (the
 * same way latest_session_id and latest_phase already worked). v_journey_summary
 * builds on this view, so it becomes one row per journey too.
 *
 * Output is unchanged for journeys that only ever had one mentor at one facility.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('
            CREATE OR REPLACE VIEW v_evaluation_group_status AS
            WITH session_item_status AS (
                SELECT
                    es.evaluation_group_id,
                    es.id AS session_id,
                    es.mentee_id,
                    es.evaluator_id,
                    es.tool_id,
                    es.district_id,
                    es.facility_id,
                    es.eval_date,
                    es.phase,
                    es.created_at AS session_created_at,
                    (es.reopen_reason IS NOT NULL) AS is_reopen,
                    SUM(CASE
                        WHEN ei.is_advanced = 0 AND sis.mentee_score >= 4 THEN 1
                        ELSE 0
                    END) AS basic_competent_items_in_session,
                    SUM(CASE
                        WHEN ei.is_advanced = 0 THEN 1
                        ELSE 0
                    END) AS basic_required_items,
                    SUM(CASE
                        WHEN sis.mentee_score >= 4 THEN 1
                        ELSE 0
                    END) AS fully_competent_items_in_session,
                    COUNT(*) AS total_items_in_session
                FROM evaluation_sessions es
                LEFT JOIN session_item_scores sis ON sis.session_id = es.id
                LEFT JOIN evaluation_items ei ON ei.id = sis.item_id
                WHERE ei.tool_id = es.tool_id
                    AND (SELECT COUNT(*) FROM tools t WHERE t.id = es.tool_id AND t.slug = "counselling") = 0
                GROUP BY es.evaluation_group_id, es.id, es.mentee_id, es.evaluator_id,
                         es.tool_id, es.district_id, es.facility_id, es.eval_date, es.phase, es.created_at,
                         es.reopen_reason
            ),
            tool_item_counts AS (
                SELECT
                    t.id AS tool_id,
                    COUNT(*) AS total_items,
                    SUM(CASE WHEN ei.is_advanced = 0 THEN 1 ELSE 0 END) AS basic_items
                FROM tools t
                JOIN evaluation_items ei ON ei.tool_id = t.id
                WHERE t.slug != "counselling"
                GROUP BY t.id
            ),
            session_competency AS (
                SELECT
                    sis.evaluation_group_id,
                    sis.session_id,
                    sis.mentee_id,
                    sis.evaluator_id,
                    sis.tool_id,
                    sis.district_id,
                    sis.facility_id,
                    sis.eval_date,
                    sis.phase,
                    sis.session_created_at,
                    sis.is_reopen,
                    (sis.basic_competent_items_in_session = tic.basic_items) AS basic_competent_in_session,
                    (sis.fully_competent_items_in_session = tic.total_items) AS fully_competent_in_session,
                    ROW_NUMBER() OVER (
                        PARTITION BY sis.evaluation_group_id, sis.tool_id
                        ORDER BY sis.eval_date ASC, sis.session_created_at ASC
                    ) AS session_number,
                    ROW_NUMBER() OVER (
                        PARTITION BY sis.evaluation_group_id, sis.tool_id
                        ORDER BY sis.eval_date DESC, sis.session_created_at DESC
                    ) AS latest_rank
                FROM session_item_status sis
                JOIN tool_item_counts tic ON tic.tool_id = sis.tool_id
            ),
            reopen_boundary AS (
                SELECT
                    evaluation_group_id,
                    tool_id,
                    COALESCE(MAX(CASE WHEN is_reopen THEN session_number END), 1) AS from_session_number
                FROM session_competency
                GROUP BY evaluation_group_id, tool_id
            ),
            first_competency AS (
                SELECT
                    sc.evaluation_group_id,
                    sc.tool_id,
                    MIN(CASE WHEN sc.basic_competent_in_session AND sc.session_number >= rb.from_session_number THEN sc.session_id END) AS first_basic_session_id,
                    MIN(CASE WHEN sc.basic_competent_in_session AND sc.session_number >= rb.from_session_number THEN sc.eval_date END) AS first_basic_date,
                    MIN(CASE WHEN sc.basic_competent_in_session AND sc.session_number >= rb.from_session_number THEN sc.session_number END) AS sessions_to_basic_competence,
                    MIN(CASE WHEN sc.fully_competent_in_session AND sc.session_number >= rb.from_session_number THEN sc.session_id END) AS first_full_session_id,
                    MIN(CASE WHEN sc.fully_competent_in_session AND sc.session_number >= rb.from_session_number THEN sc.eval_date END) AS first_full_date,
                    MIN(CASE WHEN sc.fully_competent_in_session AND sc.session_number >= rb.from_session_number THEN sc.session_number END) AS sessions_to_full_competence
                FROM session_competency sc
                JOIN reopen_boundary rb
                    ON rb.evaluation_group_id = sc.evaluation_group_id
                    AND rb.tool_id = sc.tool_id
                GROUP BY sc.evaluation_group_id, sc.tool_id
            )
            SELECT
                sc.evaluation_group_id,
                sc.mentee_id,
                MAX(CASE WHEN sc.latest_rank = 1 THEN sc.evaluator_id END) AS evaluator_id,
                sc.tool_id,
                MAX(CASE WHEN sc.latest_rank = 1 THEN sc.district_id END) AS district_id,
                MAX(CASE WHEN sc.latest_rank = 1 THEN sc.facility_id END) AS facility_id,
                MAX(CASE WHEN sc.latest_rank = 1 THEN sc.session_id END) AS latest_session_id,
                MAX(CASE WHEN sc.latest_rank = 1 THEN sc.eval_date END) AS latest_session_date,
                MAX(CASE WHEN sc.latest_rank = 1 THEN sc.phase END) AS latest_phase,
                MAX(CASE WHEN fc.first_basic_date IS NOT NULL THEN 1 ELSE 0 END) AS basic_competent,
                MAX(CASE WHEN fc.first_full_date IS NOT NULL THEN 1 ELSE 0 END) AS fully_competent,
                MAX(fc.first_basic_date) AS basic_competent_at,
                MAX(fc.sessions_to_basic_competence) AS sessions_to_basic_competence,
                MAX(DATEDIFF(fc.first_basic_date, (
                    SELECT MIN(eval_date) FROM evaluation_sessions
                    WHERE evaluation_group_id = sc.evaluation_group_id AND tool_id = sc.tool_id
                ))) AS days_to_basic_competence,
                MAX(fc.first_full_date) AS first_full_competency_date,
                MAX(fc.sessions_to_full_competence) AS sessions_to_full_competence,
                MAX(DATEDIFF(fc.first_full_date, (
                    SELECT MIN(eval_date) FROM evaluation_sessions
                    WHERE evaluation_group_id = sc.evaluation_group_id AND tool_id = sc.tool_id
                ))) AS days_to_full_competence,
                COUNT(DISTINCT sc.session_id) AS total_sessions,
                MAX(sc.session_created_at) AS last_updated
            FROM session_competency sc
            LEFT JOIN first_competency fc
                ON fc.evaluation_group_id = sc.evaluation_group_id
                AND fc.tool_id = sc.tool_id
            GROUP BY sc.evaluation_group_id, sc.mentee_id, sc.tool_id
        ');
    }

    public function down(): void
    {
        (require database_path('migrations/2026_09_29_061908_add_reopen_reason_to_evaluation_sessions.php'))->recreateStatusView();
    }
};
