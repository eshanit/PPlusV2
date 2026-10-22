<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Competency now follows the paper tool and how mentors work in the field.
 *
 * An item counts as achieved once its most recent score in the journey is 4
 * or 5. Mentors don't re-rate achieved items, so the score can come from any
 * earlier session; a later lower re-rating makes it not achieved again, and
 * N/A never replaces an earlier score.
 *
 * - basic_competent: every required (non-advanced) item in the tool is achieved.
 * - fully_competent: every item in the tool, including advanced ones, is achieved.
 *
 * Previously the view only judged items planned in the journey, and only
 * against the latest session's scores. The output columns are unchanged.
 * Mirrors getCompetencyBreakdown() in monitoring/app/composables/useCompetency.ts.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

        DB::statement(<<<'SQL'
            CREATE OR REPLACE VIEW v_evaluation_group_status AS
            WITH ordered_sessions AS (
                SELECT
                    es.*,
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
            tool_requirements AS (
                SELECT
                    ei.tool_id,
                    SUM(CASE WHEN ei.is_advanced = 0 THEN 1 ELSE 0 END) AS required_items,
                    COUNT(*) AS total_items
                FROM evaluation_items ei
                GROUP BY ei.tool_id
            ),
            scored_items AS (
                SELECT
                    os.evaluation_group_id,
                    os.session_number,
                    sis.round_index,
                    sis.id AS score_id,
                    sis.item_id,
                    sis.mentee_score,
                    ei.is_advanced
                FROM session_item_scores sis
                JOIN ordered_sessions os ON os.id = sis.session_id
                JOIN evaluation_items ei ON ei.id = sis.item_id AND ei.tool_id = os.tool_id
                WHERE sis.mentee_score IS NOT NULL
            ),
            latest_as_of AS (
                SELECT
                    point.id AS session_id,
                    si.item_id,
                    si.mentee_score,
                    si.is_advanced,
                    ROW_NUMBER() OVER (
                        PARTITION BY point.id, si.item_id
                        ORDER BY si.session_number DESC, si.round_index DESC, si.score_id DESC
                    ) AS recency
                FROM ordered_sessions point
                JOIN scored_items si
                    ON si.evaluation_group_id = point.evaluation_group_id
                    AND si.session_number <= point.session_number
            ),
            achieved_as_of AS (
                SELECT
                    session_id,
                    SUM(CASE WHEN is_advanced = 0 AND mentee_score >= 4 THEN 1 ELSE 0 END) AS basic_achieved,
                    SUM(CASE WHEN mentee_score >= 4 THEN 1 ELSE 0 END) AS total_achieved
                FROM latest_as_of
                WHERE recency = 1
                GROUP BY session_id
            ),
            session_competency AS (
                SELECT
                    os.evaluation_group_id,
                    os.id AS session_id,
                    os.mentee_id,
                    os.evaluator_id,
                    os.tool_id,
                    os.district_id,
                    os.facility_id,
                    os.eval_date,
                    os.phase,
                    os.created_at AS session_created_at,
                    os.session_number,
                    os.reopen_reason,
                    os.latest_rank,
                    CASE
                        WHEN tr.required_items > 0
                            AND COALESCE(a.basic_achieved, 0) = tr.required_items THEN 1
                        ELSE 0
                    END AS basic_met,
                    CASE
                        WHEN tr.total_items > 0
                            AND COALESCE(a.total_achieved, 0) = tr.total_items THEN 1
                        ELSE 0
                    END AS full_met
                FROM ordered_sessions os
                LEFT JOIN tool_requirements tr ON tr.tool_id = os.tool_id
                LEFT JOIN achieved_as_of a ON a.session_id = os.id
            ),
            reopen_boundary AS (
                SELECT
                    evaluation_group_id,
                    COALESCE(MAX(CASE WHEN reopen_reason IS NOT NULL THEN session_number END), 1) AS from_session_number
                FROM session_competency
                GROUP BY evaluation_group_id
            ),
            first_competency AS (
                SELECT
                    sc.evaluation_group_id,
                    MIN(CASE WHEN sc.basic_met = 1 AND sc.session_number >= rb.from_session_number THEN sc.eval_date END) AS first_basic_date,
                    MIN(CASE WHEN sc.basic_met = 1 AND sc.session_number >= rb.from_session_number THEN sc.session_number END) AS sessions_to_basic_competence,
                    MIN(CASE WHEN sc.full_met = 1 AND sc.session_number >= rb.from_session_number THEN sc.eval_date END) AS first_full_date,
                    MIN(CASE WHEN sc.full_met = 1 AND sc.session_number >= rb.from_session_number THEN sc.session_number END) AS sessions_to_full_competence
                FROM session_competency sc
                JOIN reopen_boundary rb ON rb.evaluation_group_id = sc.evaluation_group_id
                GROUP BY sc.evaluation_group_id
            ),
            journey_summary AS (
                SELECT
                    sc.evaluation_group_id,
                    sc.mentee_id,
                    sc.tool_id,
                    MAX(CASE WHEN sc.latest_rank = 1 THEN sc.evaluator_id END) AS evaluator_id,
                    MAX(CASE WHEN sc.latest_rank = 1 THEN sc.district_id END) AS district_id,
                    MAX(CASE WHEN sc.latest_rank = 1 THEN sc.facility_id END) AS facility_id,
                    MAX(CASE WHEN sc.latest_rank = 1 THEN sc.session_id END) AS latest_session_id,
                    MAX(CASE WHEN sc.latest_rank = 1 THEN sc.eval_date END) AS latest_session_date,
                    MAX(CASE WHEN sc.latest_rank = 1 THEN sc.phase END) AS latest_phase,
                    MIN(sc.eval_date) AS first_session_date,
                    COUNT(DISTINCT sc.session_id) AS total_sessions,
                    MAX(sc.session_created_at) AS last_updated,
                    MAX(CASE WHEN sc.latest_rank = 1 THEN sc.basic_met ELSE 0 END) AS basic_competent,
                    MAX(CASE WHEN sc.latest_rank = 1 THEN sc.full_met ELSE 0 END) AS fully_competent
                FROM session_competency sc
                GROUP BY sc.evaluation_group_id, sc.mentee_id, sc.tool_id
            )
            SELECT
                js.evaluation_group_id,
                js.mentee_id,
                js.evaluator_id,
                js.tool_id,
                js.district_id,
                js.facility_id,
                js.latest_session_id,
                js.latest_session_date,
                js.latest_phase,
                js.basic_competent,
                js.fully_competent,
                CASE WHEN js.basic_competent = 1 THEN fc.first_basic_date END AS basic_competent_at,
                CASE WHEN js.basic_competent = 1 THEN fc.sessions_to_basic_competence END AS sessions_to_basic_competence,
                CASE WHEN js.basic_competent = 1 THEN DATEDIFF(fc.first_basic_date, js.first_session_date) END AS days_to_basic_competence,
                CASE WHEN js.fully_competent = 1 THEN fc.first_full_date END AS first_full_competency_date,
                CASE WHEN js.fully_competent = 1 THEN fc.sessions_to_full_competence END AS sessions_to_full_competence,
                CASE WHEN js.fully_competent = 1 THEN DATEDIFF(fc.first_full_date, js.first_session_date) END AS days_to_full_competence,
                js.total_sessions,
                js.last_updated
            FROM journey_summary js
            LEFT JOIN first_competency fc ON fc.evaluation_group_id = js.evaluation_group_id
            SQL);
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

        // Restore the definition from 2026_10_11_000001_add_round_aware_session_reporting.
        DB::statement(<<<'SQL'
            CREATE OR REPLACE VIEW v_evaluation_group_status AS
            WITH ordered_sessions AS (
                SELECT
                    es.*,
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
            selected_items AS (
                SELECT DISTINCT
                    es.evaluation_group_id,
                    es.id AS session_id,
                    ei.id AS item_id,
                    es.session_number
                FROM ordered_sessions es
                JOIN evaluation_items ei ON ei.tool_id = es.tool_id
                JOIN tools t ON t.id = ei.tool_id AND t.slug != "counselling"
                WHERE
                    (
                        es.selected_item_slugs IS NOT NULL
                        AND JSON_CONTAINS(es.selected_item_slugs, JSON_QUOTE(ei.slug))
                    )
                    OR (
                        es.selected_item_slugs IS NULL
                        AND EXISTS (
                            SELECT 1 FROM session_item_scores legacy_score
                            WHERE legacy_score.session_id = es.id
                                AND legacy_score.item_id = ei.id
                        )
                    )
            ),
            plan_to_date AS (
                SELECT DISTINCT
                    current_session.evaluation_group_id,
                    current_session.id AS session_id,
                    current_session.session_number,
                    current_session.mentee_id,
                    current_session.evaluator_id,
                    current_session.tool_id,
                    current_session.district_id,
                    current_session.facility_id,
                    current_session.eval_date,
                    current_session.phase,
                    current_session.created_at AS session_created_at,
                    current_session.reopen_reason,
                    current_session.latest_rank,
                    selected.item_id
                FROM ordered_sessions current_session
                JOIN selected_items selected
                    ON selected.evaluation_group_id = current_session.evaluation_group_id
                    AND selected.session_number <= current_session.session_number
            ),
            ranked_scores AS (
                SELECT
                    sis.*,
                    ROW_NUMBER() OVER (
                        PARTITION BY sis.session_id, sis.item_id
                        ORDER BY (sis.mentee_score IS NULL), sis.round_index DESC, sis.id DESC
                    ) AS item_round_rank
                FROM session_item_scores sis
            ),
            session_competency AS (
                SELECT
                    p.evaluation_group_id,
                    p.session_id,
                    p.mentee_id,
                    p.evaluator_id,
                    p.tool_id,
                    p.district_id,
                    p.facility_id,
                    p.eval_date,
                    p.phase,
                    p.session_created_at,
                    p.session_number,
                    p.reopen_reason,
                    p.latest_rank,
                    SUM(CASE WHEN ei.is_advanced = 0 THEN 1 ELSE 0 END) AS basic_required_items,
                    SUM(CASE WHEN ei.is_advanced = 0 AND rs.mentee_score >= 4 THEN 1 ELSE 0 END) AS basic_competent_items,
                    COUNT(*) AS total_required_items,
                    SUM(CASE WHEN rs.mentee_score >= 4 THEN 1 ELSE 0 END) AS fully_competent_items
                FROM plan_to_date p
                JOIN evaluation_items ei ON ei.id = p.item_id
                LEFT JOIN ranked_scores rs
                    ON rs.session_id = p.session_id
                    AND rs.item_id = p.item_id
                    AND rs.item_round_rank = 1
                GROUP BY
                    p.evaluation_group_id, p.session_id, p.mentee_id,
                    p.evaluator_id, p.tool_id, p.district_id, p.facility_id,
                    p.eval_date, p.phase, p.session_created_at, p.session_number,
                    p.reopen_reason, p.latest_rank
            ),
            reopen_boundary AS (
                SELECT
                    evaluation_group_id,
                    COALESCE(MAX(CASE WHEN reopen_reason IS NOT NULL THEN session_number END), 1) AS from_session_number
                FROM session_competency
                GROUP BY evaluation_group_id
            ),
            first_competency AS (
                SELECT
                    sc.evaluation_group_id,
                    MIN(CASE
                        WHEN sc.basic_competent_items = sc.basic_required_items
                            AND sc.basic_required_items > 0
                            AND sc.session_number >= rb.from_session_number
                        THEN sc.session_id
                    END) AS first_basic_session_id,
                    MIN(CASE
                        WHEN sc.basic_competent_items = sc.basic_required_items
                            AND sc.basic_required_items > 0
                            AND sc.session_number >= rb.from_session_number
                        THEN sc.eval_date
                    END) AS first_basic_date,
                    MIN(CASE
                        WHEN sc.basic_competent_items = sc.basic_required_items
                            AND sc.basic_required_items > 0
                            AND sc.session_number >= rb.from_session_number
                        THEN sc.session_number
                    END) AS sessions_to_basic_competence,
                    MIN(CASE
                        WHEN sc.fully_competent_items = sc.total_required_items
                            AND sc.total_required_items > 0
                            AND sc.session_number >= rb.from_session_number
                        THEN sc.session_id
                    END) AS first_full_session_id,
                    MIN(CASE
                        WHEN sc.fully_competent_items = sc.total_required_items
                            AND sc.total_required_items > 0
                            AND sc.session_number >= rb.from_session_number
                        THEN sc.eval_date
                    END) AS first_full_date,
                    MIN(CASE
                        WHEN sc.fully_competent_items = sc.total_required_items
                            AND sc.total_required_items > 0
                            AND sc.session_number >= rb.from_session_number
                        THEN sc.session_number
                    END) AS sessions_to_full_competence
                FROM session_competency sc
                JOIN reopen_boundary rb ON rb.evaluation_group_id = sc.evaluation_group_id
                GROUP BY sc.evaluation_group_id
            ),
            journey_summary AS (
                SELECT
                    sc.evaluation_group_id,
                    sc.mentee_id,
                    sc.tool_id,
                    MAX(CASE WHEN sc.latest_rank = 1 THEN sc.evaluator_id END) AS evaluator_id,
                    MAX(CASE WHEN sc.latest_rank = 1 THEN sc.district_id END) AS district_id,
                    MAX(CASE WHEN sc.latest_rank = 1 THEN sc.facility_id END) AS facility_id,
                    MAX(CASE WHEN sc.latest_rank = 1 THEN sc.session_id END) AS latest_session_id,
                    MAX(CASE WHEN sc.latest_rank = 1 THEN sc.eval_date END) AS latest_session_date,
                    MAX(CASE WHEN sc.latest_rank = 1 THEN sc.phase END) AS latest_phase,
                    MIN(sc.eval_date) AS first_session_date,
                    COUNT(DISTINCT sc.session_id) AS total_sessions,
                    MAX(sc.session_created_at) AS last_updated,
                    MAX(CASE
                        WHEN sc.latest_rank = 1
                            AND sc.basic_competent_items = sc.basic_required_items
                            AND sc.basic_required_items > 0 THEN 1
                        ELSE 0
                    END) AS basic_competent,
                    MAX(CASE
                        WHEN sc.latest_rank = 1
                            AND sc.fully_competent_items = sc.total_required_items
                            AND sc.total_required_items > 0 THEN 1
                        ELSE 0
                    END) AS fully_competent
                FROM session_competency sc
                GROUP BY sc.evaluation_group_id, sc.mentee_id, sc.tool_id
            )
            SELECT
                js.evaluation_group_id,
                js.mentee_id,
                js.evaluator_id,
                js.tool_id,
                js.district_id,
                js.facility_id,
                js.latest_session_id,
                js.latest_session_date,
                js.latest_phase,
                js.basic_competent,
                js.fully_competent,
                CASE WHEN js.basic_competent = 1 THEN fc.first_basic_date END AS basic_competent_at,
                CASE WHEN js.basic_competent = 1 THEN fc.sessions_to_basic_competence END AS sessions_to_basic_competence,
                CASE WHEN js.basic_competent = 1 THEN DATEDIFF(fc.first_basic_date, js.first_session_date) END AS days_to_basic_competence,
                CASE WHEN js.fully_competent = 1 THEN fc.first_full_date END AS first_full_competency_date,
                CASE WHEN js.fully_competent = 1 THEN fc.sessions_to_full_competence END AS sessions_to_full_competence,
                CASE WHEN js.fully_competent = 1 THEN DATEDIFF(fc.first_full_date, js.first_session_date) END AS days_to_full_competence,
                js.total_sessions,
                js.last_updated
            FROM journey_summary js
            LEFT JOIN first_competency fc ON fc.evaluation_group_id = js.evaluation_group_id
            SQL);
    }
};
