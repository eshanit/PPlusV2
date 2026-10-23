<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Reporting views for partial evaluation.
 *
 * Mentors now evaluate only the competencies they observed, so one session's
 * average covers a different set of items each time and cannot be compared
 * from session to session. These views add measures that stay comparable:
 *
 * - v_journey_progress: for every session, the journey's standing at that
 *   point, using each tool item's latest score so far (the competency rule):
 *   required items achieved, all items achieved, items ever evaluated, and the
 *   average of those latest scores.
 * - v_journey_summary: adds the latest session's standing (required_items,
 *   required_achieved, total_items, total_achieved, items_evaluated,
 *   current_avg_score). latest_avg_score stays, now limited to the tool's own
 *   items; it previously mixed in counselling scores.
 * - v_day_progress: same columns, but round-to-round and day-to-day deltas only
 *   compare items scored in both, with counts of the items compared.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

        DB::statement(<<<'SQL'
            CREATE OR REPLACE VIEW v_journey_progress AS
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
            standing_as_of AS (
                SELECT
                    session_id,
                    COUNT(*) AS items_evaluated,
                    SUM(CASE WHEN is_advanced = 0 AND mentee_score >= 4 THEN 1 ELSE 0 END) AS required_achieved,
                    SUM(CASE WHEN mentee_score >= 4 THEN 1 ELSE 0 END) AS total_achieved,
                    ROUND(AVG(mentee_score), 2) AS current_avg_score
                FROM latest_as_of
                WHERE recency = 1
                GROUP BY session_id
            )
            SELECT
                os.evaluation_group_id,
                os.id AS session_id,
                os.session_number,
                os.latest_rank,
                os.eval_date,
                os.mentee_id,
                os.tool_id,
                os.district_id,
                os.facility_id,
                COALESCE(tr.required_items, 0) AS required_items,
                COALESCE(tr.total_items, 0) AS total_items,
                COALESCE(sa.required_achieved, 0) AS required_achieved,
                COALESCE(sa.total_achieved, 0) AS total_achieved,
                COALESCE(sa.items_evaluated, 0) AS items_evaluated,
                sa.current_avg_score
            FROM ordered_sessions os
            LEFT JOIN tool_requirements tr ON tr.tool_id = os.tool_id
            LEFT JOIN standing_as_of sa ON sa.session_id = os.id
            SQL);

        DB::statement(<<<'SQL'
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
                vgs.basic_competent_at,
                vgs.sessions_to_basic_competence,
                vgs.days_to_basic_competence,
                vgs.first_full_competency_date,
                vgs.sessions_to_full_competence,
                vgs.days_to_full_competence,
                vgs.total_sessions,
                CASE
                    WHEN vgs.fully_competent = 1 THEN "fully_competent"
                    WHEN vgs.basic_competent = 1 THEN "basic_competent"
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
                COALESCE(ROUND(AVG(sis.mentee_score), 2), NULL) AS latest_avg_score,
                COALESCE(COUNT(DISTINCT sis.item_id), 0) AS latest_scored_items,
                COALESCE(ge_open.open_gaps, 0) AS open_gaps,
                COALESCE(ge_resolved.resolved_gaps, 0) AS resolved_gaps,
                vgs.last_updated,
                jp.required_items,
                jp.required_achieved,
                jp.total_items,
                jp.total_achieved,
                jp.items_evaluated,
                jp.current_avg_score
            FROM v_evaluation_group_status vgs
            JOIN tools t ON t.id = vgs.tool_id
            LEFT JOIN districts d ON d.id = vgs.district_id
            LEFT JOIN facilities f ON f.id = vgs.facility_id
            JOIN users u ON u.id = vgs.mentee_id
            JOIN users ev ON ev.id = vgs.evaluator_id
            LEFT JOIN (
                v_session_item_scores sis
                JOIN evaluation_items lei ON lei.id = sis.item_id
            ) ON sis.session_id = vgs.latest_session_id AND lei.tool_id = vgs.tool_id
            LEFT JOIN v_journey_progress jp ON jp.session_id = vgs.latest_session_id
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
                     vgs.latest_phase, vgs.basic_competent, vgs.fully_competent, vgs.basic_competent_at,
                     vgs.sessions_to_basic_competence, vgs.days_to_basic_competence, vgs.first_full_competency_date,
                     vgs.sessions_to_full_competence, vgs.days_to_full_competence, vgs.total_sessions,
                     t.label, t.slug, d.name, f.name, u.firstname, u.lastname, u.email,
                     ev.firstname, ev.lastname, ge_open.open_gaps, ge_resolved.resolved_gaps,
                     vgs.last_updated, jp.required_items, jp.required_achieved, jp.total_items,
                     jp.total_achieved, jp.items_evaluated, jp.current_avg_score
            SQL);

        DB::statement(<<<'SQL'
            CREATE OR REPLACE VIEW v_day_progress AS
            WITH rounds AS (
                SELECT
                    es.evaluation_group_id,
                    es.tool_id,
                    es.mentee_id,
                    es.district_id,
                    es.facility_id,
                    es.eval_date,
                    es.id AS session_id,
                    es.day_round,
                    es.created_at AS session_created_at,
                    sis.round_index
                FROM evaluation_sessions es
                JOIN session_item_scores sis ON sis.session_id = es.id
                JOIN evaluation_items ei ON ei.id = sis.item_id AND ei.tool_id = es.tool_id
                GROUP BY
                    es.evaluation_group_id, es.tool_id, es.mentee_id,
                    es.district_id, es.facility_id, es.eval_date,
                    es.id, es.day_round, es.created_at, sis.round_index
            ),
            numbered_rounds AS (
                SELECT
                    r.*,
                    ROW_NUMBER() OVER (
                        PARTITION BY r.evaluation_group_id, r.eval_date
                        ORDER BY COALESCE(r.day_round, 999999), r.session_created_at, r.round_index
                    ) AS day_round_number,
                    COUNT(*) OVER (
                        PARTITION BY r.evaluation_group_id, r.eval_date
                    ) AS day_round_count
                FROM rounds r
            ),
            round_scores AS (
                SELECT
                    nr.evaluation_group_id,
                    nr.eval_date,
                    nr.day_round_number,
                    nr.day_round_count,
                    sis.item_id,
                    sis.mentee_score
                FROM numbered_rounds nr
                JOIN session_item_scores sis
                    ON sis.session_id = nr.session_id AND sis.round_index = nr.round_index
                JOIN evaluation_items ei ON ei.id = sis.item_id AND ei.tool_id = nr.tool_id
                WHERE sis.mentee_score IS NOT NULL
            ),
            days AS (
                SELECT
                    nr.evaluation_group_id,
                    nr.tool_id,
                    nr.mentee_id,
                    nr.district_id,
                    nr.facility_id,
                    nr.eval_date,
                    MAX(nr.day_round_count) AS rounds_that_day
                FROM numbered_rounds nr
                GROUP BY nr.evaluation_group_id, nr.tool_id, nr.mentee_id, nr.district_id, nr.facility_id, nr.eval_date
            ),
            day_scores AS (
                SELECT evaluation_group_id, eval_date, ROUND(AVG(mentee_score), 2) AS day_avg_score
                FROM round_scores
                GROUP BY evaluation_group_id, eval_date
            ),
            day_sequence AS (
                SELECT
                    d.*,
                    LAG(d.eval_date) OVER (PARTITION BY d.evaluation_group_id ORDER BY d.eval_date) AS prev_eval_date
                FROM days d
            ),
            -- Within a day: first round vs last round, on items scored in both.
            intra_day AS (
                SELECT
                    f.evaluation_group_id,
                    f.eval_date,
                    ROUND(AVG(f.mentee_score), 2) AS first_round_avg,
                    ROUND(AVG(l.mentee_score), 2) AS last_round_avg,
                    COUNT(*) AS intra_day_items
                FROM round_scores f
                JOIN round_scores l
                    ON l.evaluation_group_id = f.evaluation_group_id
                    AND l.eval_date = f.eval_date
                    AND l.item_id = f.item_id
                    AND l.day_round_number = l.day_round_count
                WHERE f.day_round_number = 1 AND f.day_round_count > 1
                GROUP BY f.evaluation_group_id, f.eval_date
            ),
            -- Day to day: this day's first round vs the previous day's last round, on items scored in both.
            cross_day AS (
                SELECT
                    ds.evaluation_group_id,
                    ds.eval_date,
                    ROUND(AVG(prev.mentee_score), 2) AS prev_day_last_round_avg,
                    ROUND(AVG(cur.mentee_score) - AVG(prev.mentee_score), 2) AS day_over_day_delta,
                    COUNT(*) AS day_over_day_items
                FROM day_sequence ds
                JOIN round_scores cur
                    ON cur.evaluation_group_id = ds.evaluation_group_id
                    AND cur.eval_date = ds.eval_date
                    AND cur.day_round_number = 1
                JOIN round_scores prev
                    ON prev.evaluation_group_id = ds.evaluation_group_id
                    AND prev.eval_date = ds.prev_eval_date
                    AND prev.day_round_number = prev.day_round_count
                    AND prev.item_id = cur.item_id
                GROUP BY ds.evaluation_group_id, ds.eval_date
            )
            SELECT
                ds.evaluation_group_id,
                ds.tool_id,
                ds.mentee_id,
                ds.district_id,
                ds.facility_id,
                ds.eval_date,
                ds.rounds_that_day,
                sc.day_avg_score,
                CASE WHEN ds.rounds_that_day > 1 THEN idy.first_round_avg ELSE sc.day_avg_score END AS first_round_avg,
                CASE WHEN ds.rounds_that_day > 1 THEN idy.last_round_avg ELSE sc.day_avg_score END AS last_round_avg,
                CASE
                    WHEN ds.rounds_that_day > 1 THEN ROUND(idy.last_round_avg - idy.first_round_avg, 2)
                    ELSE 0
                END AS intra_day_delta,
                COALESCE(idy.intra_day_items, 0) AS intra_day_items,
                ds.prev_eval_date,
                cd.prev_day_last_round_avg,
                cd.day_over_day_delta,
                COALESCE(cd.day_over_day_items, 0) AS day_over_day_items
            FROM day_sequence ds
            LEFT JOIN day_scores sc ON sc.evaluation_group_id = ds.evaluation_group_id AND sc.eval_date = ds.eval_date
            LEFT JOIN intra_day idy ON idy.evaluation_group_id = ds.evaluation_group_id AND idy.eval_date = ds.eval_date
            LEFT JOIN cross_day cd ON cd.evaluation_group_id = ds.evaluation_group_id AND cd.eval_date = ds.eval_date
            SQL);
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

        // Restore the previous definitions.
        DB::statement(<<<'SQL'
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
                vgs.basic_competent_at,
                vgs.sessions_to_basic_competence,
                vgs.days_to_basic_competence,
                vgs.first_full_competency_date,
                vgs.sessions_to_full_competence,
                vgs.days_to_full_competence,
                vgs.total_sessions,
                CASE
                    WHEN vgs.fully_competent = 1 THEN "fully_competent"
                    WHEN vgs.basic_competent = 1 THEN "basic_competent"
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
                COALESCE(ROUND(AVG(sis.mentee_score), 2), NULL) AS latest_avg_score,
                COALESCE(COUNT(DISTINCT sis.item_id), 0) AS latest_scored_items,
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
                     vgs.latest_phase, vgs.basic_competent, vgs.fully_competent, vgs.basic_competent_at,
                     vgs.sessions_to_basic_competence, vgs.days_to_basic_competence, vgs.first_full_competency_date,
                     vgs.sessions_to_full_competence, vgs.days_to_full_competence, vgs.total_sessions,
                     t.label, t.slug, d.name, f.name, u.firstname, u.lastname, u.email,
                     ev.firstname, ev.lastname, ge_open.open_gaps, ge_resolved.resolved_gaps,
                     vgs.last_updated
            SQL);

        DB::statement(<<<'SQL'
            CREATE OR REPLACE VIEW v_day_progress AS
            WITH round_averages AS (
                SELECT
                    es.evaluation_group_id,
                    es.tool_id,
                    es.mentee_id,
                    es.district_id,
                    es.facility_id,
                    es.eval_date,
                    es.id AS session_id,
                    es.day_round,
                    es.created_at AS session_created_at,
                    sis.round_index,
                    ROUND(AVG(sis.mentee_score), 2) AS avg_mentee_score
                FROM evaluation_sessions es
                JOIN session_item_scores sis ON sis.session_id = es.id
                JOIN evaluation_items ei ON ei.id = sis.item_id
                JOIN tools t ON t.id = ei.tool_id AND t.slug != "counselling"
                GROUP BY
                    es.evaluation_group_id, es.tool_id, es.mentee_id,
                    es.district_id, es.facility_id, es.eval_date,
                    es.id, es.day_round, es.created_at, sis.round_index
            ),
            numbered_rounds AS (
                SELECT
                    ra.*,
                    ROW_NUMBER() OVER (
                        PARTITION BY ra.evaluation_group_id, ra.eval_date
                        ORDER BY
                            COALESCE(ra.day_round, 999999),
                            ra.session_created_at,
                            ra.round_index
                    ) AS day_round_number,
                    COUNT(*) OVER (
                        PARTITION BY ra.evaluation_group_id, ra.eval_date
                    ) AS day_round_count
                FROM round_averages ra
            ),
            day_averages AS (
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
                FROM numbered_rounds
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
            FROM day_averages
            SQL);

        DB::statement('DROP VIEW IF EXISTS v_journey_progress');
    }
};
