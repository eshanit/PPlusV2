<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. v_latest_item_scores: carry-forward per journey+item
        // For each (evaluation_group_id, item_id), get most recent non-null score
        DB::statement('
            CREATE OR REPLACE VIEW v_latest_item_scores AS
            WITH ranked AS (
                SELECT
                    es.evaluation_group_id,
                    es.mentee_id,
                    es.tool_id,
                    es.district_id,
                    es.facility_id,
                    sis.item_id,
                    sis.mentee_score,
                    es.eval_date,
                    ROW_NUMBER() OVER (
                        PARTITION BY es.evaluation_group_id, sis.item_id
                        ORDER BY es.eval_date DESC, es.created_at DESC
                    ) AS rn
                FROM session_item_scores sis
                JOIN evaluation_sessions es ON es.id = sis.session_id
                WHERE sis.mentee_score IS NOT NULL
            )
            SELECT
                evaluation_group_id,
                mentee_id,
                tool_id,
                district_id,
                facility_id,
                item_id,
                mentee_score,
                eval_date AS score_date
            FROM ranked
            WHERE rn = 1
        ');

        // 2. v_evaluation_group_status: per-session competency
        // Per-session logic: a session meets basic competency if ALL non-advanced items in that session >= 4
        // This is STRICTER than carry-forward (different from monitoring app)
        DB::statement('
            CREATE OR REPLACE VIEW v_evaluation_group_status AS
            WITH session_item_status AS (
                -- For each session, check if all non-advanced items are >= 4
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
                    -- Count how many non-advanced items are >= 4 in this session
                    SUM(CASE 
                        WHEN ei.is_advanced = 0 AND sis.mentee_score >= 4 THEN 1 
                        ELSE 0 
                    END) AS basic_competent_items_in_session,
                    -- Total non-advanced items for this tool
                    SUM(CASE 
                        WHEN ei.is_advanced = 0 THEN 1 
                        ELSE 0 
                    END) AS basic_required_items,
                    -- Count all items >= 4 (including advanced) in this session
                    SUM(CASE 
                        WHEN sis.mentee_score >= 4 THEN 1 
                        ELSE 0 
                    END) AS fully_competent_items_in_session,
                    -- Total items for this tool (excluding counselling)
                    COUNT(*) AS total_items_in_session
                FROM evaluation_sessions es
                LEFT JOIN session_item_scores sis ON sis.session_id = es.id
                LEFT JOIN evaluation_items ei ON ei.id = sis.item_id
                WHERE ei.tool_id = es.tool_id
                    AND (SELECT COUNT(*) FROM tools t WHERE t.id = es.tool_id AND t.slug = "counselling") = 0
                GROUP BY es.evaluation_group_id, es.id, es.mentee_id, es.evaluator_id, 
                         es.tool_id, es.district_id, es.facility_id, es.eval_date, es.phase, es.created_at
            ),
            tool_item_counts AS (
                -- Total item counts per tool
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
                -- Flag which sessions meet basic/full competency
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
                    (sis.basic_competent_items_in_session = tic.basic_items) AS basic_competent_in_session,
                    (sis.fully_competent_items_in_session = tic.total_items) AS fully_competent_in_session,
                    ROW_NUMBER() OVER (
                        PARTITION BY sis.evaluation_group_id, sis.tool_id 
                        ORDER BY sis.eval_date ASC, sis.session_created_at ASC
                    ) AS session_number
                FROM session_item_status sis
                JOIN tool_item_counts tic ON tic.tool_id = sis.tool_id
            ),
            first_competency AS (
                -- Find first session where basic competency was achieved
                SELECT
                    evaluation_group_id,
                    tool_id,
                    MIN(CASE WHEN basic_competent_in_session THEN session_id END) AS first_basic_session_id,
                    MIN(CASE WHEN basic_competent_in_session THEN eval_date END) AS first_basic_date,
                    MIN(CASE WHEN basic_competent_in_session THEN session_number END) AS sessions_to_basic_competence,
                    MIN(CASE WHEN fully_competent_in_session THEN session_id END) AS first_full_session_id,
                    MIN(CASE WHEN fully_competent_in_session THEN eval_date END) AS first_full_date,
                    MIN(CASE WHEN fully_competent_in_session THEN session_number END) AS sessions_to_full_competence
                FROM session_competency
                GROUP BY evaluation_group_id, tool_id
            )
            SELECT
                sc.evaluation_group_id,
                sc.mentee_id,
                sc.evaluator_id,
                sc.tool_id,
                sc.district_id,
                sc.facility_id,
                sc.session_id AS latest_session_id,
                sc.eval_date AS latest_session_date,
                sc.phase AS latest_phase,
                MAX(CASE WHEN sc.basic_competent_in_session THEN 1 ELSE 0 END) AS basic_competent,
                MAX(CASE WHEN sc.fully_competent_in_session THEN 1 ELSE 0 END) AS fully_competent,
                COALESCE(fc.first_basic_date, NULL) AS first_competency_date,
                COALESCE(fc.sessions_to_basic_competence, NULL) AS sessions_to_basic_competence,
                COALESCE(DATEDIFF(fc.first_basic_date, (
                    SELECT MIN(eval_date) FROM evaluation_sessions 
                    WHERE evaluation_group_id = sc.evaluation_group_id AND tool_id = sc.tool_id
                )), NULL) AS days_to_basic_competence,
                COALESCE(fc.first_full_date, NULL) AS first_full_competency_date,
                COALESCE(fc.sessions_to_full_competence, NULL) AS sessions_to_full_competence,
                COALESCE(DATEDIFF(fc.first_full_date, (
                    SELECT MIN(eval_date) FROM evaluation_sessions 
                    WHERE evaluation_group_id = sc.evaluation_group_id AND tool_id = sc.tool_id
                )), NULL) AS days_to_full_competence,
                COUNT(DISTINCT sc.session_id) AS total_sessions,
                MAX(sc.session_created_at) AS last_updated
            FROM session_competency sc
            LEFT JOIN first_competency fc 
                ON fc.evaluation_group_id = sc.evaluation_group_id 
                AND fc.tool_id = sc.tool_id
            GROUP BY sc.evaluation_group_id, sc.mentee_id, sc.evaluator_id, sc.tool_id,
                     sc.district_id, sc.facility_id
        ');

        // 3. v_journey_summary: one row per mentee+tool journey with all summary stats
        DB::statement('
            CREATE OR REPLACE VIEW v_journey_summary AS
            SELECT
                vgs.evaluation_group_id,
                vgs.mentee_id,
                vgs.evaluator_id,
                vgs.tool_id,
                vgs.district_id,
                vgs.facility_id,
                vgs.latest_session_id,
                vgs.latest_session_date,
                vgs.latest_phase,
                vgs.basic_competent,
                vgs.fully_competent,
                vgs.first_competency_date,
                vgs.sessions_to_basic_competence,
                vgs.days_to_basic_competence,
                vgs.first_full_competency_date,
                vgs.sessions_to_full_competence,
                vgs.days_to_full_competence,
                vgs.total_sessions,
                CASE 
                    WHEN vgs.basic_competent = 1 THEN "basic_competent"
                    WHEN vgs.fully_competent = 1 THEN "fully_competent"
                    ELSE "in_progress"
                END AS competency_status,
                t.label AS tool_label,
                t.slug AS tool_slug,
                d.name AS district_name,
                f.name AS facility_name,
                u.firstname AS mentee_firstname,
                u.lastname AS mentee_lastname,
                u.email AS mentee_email,
                ev.firstname AS evaluator_firstname,
                ev.lastname AS evaluator_lastname,
                -- Latest session average score (across all items in that session)
                COALESCE(ROUND(AVG(sis.mentee_score), 2), NULL) AS latest_avg_score,
                COALESCE(COUNT(DISTINCT sis.item_id), 0) AS latest_scored_items,
                -- Gap counts
                COALESCE(ge_open.open_gaps, 0) AS open_gaps,
                COALESCE(ge_resolved.resolved_gaps, 0) AS resolved_gaps,
                vgs.last_updated
            FROM v_evaluation_group_status vgs
            JOIN tools t ON t.id = vgs.tool_id
            LEFT JOIN districts d ON d.id = vgs.district_id
            LEFT JOIN facilities f ON f.id = vgs.facility_id
            JOIN users u ON u.id = vgs.mentee_id
            JOIN users ev ON ev.id = vgs.evaluator_id
            LEFT JOIN session_item_scores sis ON sis.session_id = vgs.latest_session_id
            LEFT JOIN (
                SELECT
                    evaluation_group_id,
                    COUNT(*) AS open_gaps
                FROM gap_entries
                WHERE resolved_at IS NULL
                GROUP BY evaluation_group_id
            ) ge_open ON ge_open.evaluation_group_id = vgs.evaluation_group_id
            LEFT JOIN (
                SELECT
                    evaluation_group_id,
                    COUNT(*) AS resolved_gaps
                FROM gap_entries
                WHERE resolved_at IS NOT NULL
                GROUP BY evaluation_group_id
            ) ge_resolved ON ge_resolved.evaluation_group_id = vgs.evaluation_group_id
            GROUP BY vgs.evaluation_group_id, vgs.mentee_id, vgs.evaluator_id, vgs.tool_id,
                     vgs.district_id, vgs.facility_id, vgs.latest_session_id, vgs.latest_session_date,
                     vgs.latest_phase, vgs.basic_competent, vgs.fully_competent, vgs.first_competency_date,
                     vgs.sessions_to_basic_competence, vgs.days_to_basic_competence, vgs.first_full_competency_date,
                     vgs.sessions_to_full_competence, vgs.days_to_full_competence, vgs.total_sessions,
                     t.label, t.slug, d.name, f.name, u.firstname, u.lastname, u.email,
                     ev.firstname, ev.lastname, ge_open.open_gaps, ge_resolved.resolved_gaps,
                     vgs.last_updated
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS v_journey_summary');
        DB::statement('DROP VIEW IF EXISTS v_evaluation_group_status');
        DB::statement('DROP VIEW IF EXISTS v_latest_item_scores');
    }
};
