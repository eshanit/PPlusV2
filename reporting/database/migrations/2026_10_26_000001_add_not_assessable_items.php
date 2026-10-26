<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Not assessable competencies (client request, 2026-10-26; see NOT_ASSESSABLE.md).
 *
 * A mentor can mark a tool item as not assessable at the mentee's facility,
 * with a reason code and an optional note. The mark is stored on the item's
 * score row with mentee_score NULL. Each item's latest entry, a score or a
 * mark, decides: a later score lifts a mark, and N/A (NULL with no reason)
 * neither sets nor lifts one.
 *
 * Marked items are left out of the competency requirement:
 * - basic_competent: every required item that isn't marked is achieved, and at
 *   least one required item is assessable.
 * - fully_competent: the same over every item, advanced included.
 *
 * Views changed, with the same rule as getCompetencyBreakdown() and
 * getNotAssessableItems() in monitoring/app/composables/useCompetency.ts:
 * - v_evaluation_group_status: adds not_assessable_items.
 * - v_journey_progress: required_items and total_items now exclude marked
 *   items; adds not_assessable_items. v_journey_summary passes it through.
 * - v_item_coverage: coverage_status 'not_assessable', with the reason and note.
 * - v_session_item_scores: adds the reason and note.
 * - v_latest_item_scores: an item whose latest entry is a mark has no row, so
 *   alerts and watchlists skip it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('session_item_scores', function (Blueprint $table) {
            $table->string('not_assessable_reason', 40)->nullable()->after('mentee_score');
            $table->text('not_assessable_note')->nullable()->after('not_assessable_reason');
        });

        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

        DB::statement(<<<'SQL'
            CREATE OR REPLACE VIEW v_session_item_scores AS
            WITH ranked_scores AS (
                SELECT
                    sis.id,
                    sis.session_id,
                    sis.round_index,
                    sis.item_id,
                    sis.mentee_score,
                    sis.notes,
                    sis.not_assessable_reason,
                    sis.not_assessable_note,
                    ROW_NUMBER() OVER (
                        PARTITION BY sis.session_id, sis.item_id
                        ORDER BY (sis.mentee_score IS NULL), sis.round_index DESC, sis.id DESC
                    ) AS item_round_rank
                FROM session_item_scores sis
            )
            SELECT id, session_id, round_index, item_id, mentee_score, notes, item_round_rank,
                not_assessable_reason, not_assessable_note
            FROM ranked_scores
            WHERE item_round_rank = 1
            SQL);

        DB::statement(<<<'SQL'
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
                        ORDER BY es.eval_date DESC, COALESCE(es.day_round, 999999) DESC, es.created_at DESC,
                            sis.round_index DESC, sis.id DESC
                    ) AS rn
                FROM session_item_scores sis
                JOIN evaluation_sessions es ON es.id = sis.session_id
                WHERE sis.mentee_score IS NOT NULL OR sis.not_assessable_reason IS NOT NULL
            )
            SELECT evaluation_group_id, mentee_id, tool_id, district_id, facility_id, item_id, mentee_score,
                eval_date AS score_date
            FROM ranked
            WHERE rn = 1 AND mentee_score IS NOT NULL
            SQL);

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
                    CASE WHEN sis.mentee_score IS NULL THEN 1 ELSE 0 END AS is_mark,
                    ei.is_advanced
                FROM session_item_scores sis
                JOIN ordered_sessions os ON os.id = sis.session_id
                JOIN evaluation_items ei ON ei.id = sis.item_id AND ei.tool_id = os.tool_id
                WHERE sis.mentee_score IS NOT NULL OR sis.not_assessable_reason IS NOT NULL
            ),
            latest_as_of AS (
                SELECT
                    point.id AS session_id,
                    si.item_id,
                    si.mentee_score,
                    si.is_mark,
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
                    SUM(CASE WHEN mentee_score >= 4 THEN 1 ELSE 0 END) AS total_achieved,
                    SUM(CASE WHEN is_mark = 1 AND is_advanced = 0 THEN 1 ELSE 0 END) AS required_marked,
                    SUM(is_mark) AS total_marked
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
                        WHEN tr.required_items - COALESCE(a.required_marked, 0) > 0
                            AND COALESCE(a.basic_achieved, 0) = tr.required_items - COALESCE(a.required_marked, 0) THEN 1
                        ELSE 0
                    END AS basic_met,
                    CASE
                        WHEN tr.total_items - COALESCE(a.total_marked, 0) > 0
                            AND COALESCE(a.total_achieved, 0) = tr.total_items - COALESCE(a.total_marked, 0) THEN 1
                        ELSE 0
                    END AS full_met,
                    COALESCE(a.total_marked, 0) AS not_assessable_items
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
                    MAX(CASE WHEN sc.latest_rank = 1 THEN sc.full_met ELSE 0 END) AS fully_competent,
                    MAX(CASE WHEN sc.latest_rank = 1 THEN sc.not_assessable_items ELSE 0 END) AS not_assessable_items
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
                js.last_updated,
                js.not_assessable_items
            FROM journey_summary js
            LEFT JOIN first_competency fc ON fc.evaluation_group_id = js.evaluation_group_id
            SQL);

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
                    CASE WHEN sis.mentee_score IS NULL THEN 1 ELSE 0 END AS is_mark,
                    ei.is_advanced
                FROM session_item_scores sis
                JOIN ordered_sessions os ON os.id = sis.session_id
                JOIN evaluation_items ei ON ei.id = sis.item_id AND ei.tool_id = os.tool_id
                WHERE sis.mentee_score IS NOT NULL OR sis.not_assessable_reason IS NOT NULL
            ),
            latest_as_of AS (
                SELECT
                    point.id AS session_id,
                    si.item_id,
                    si.mentee_score,
                    si.is_mark,
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
                    COUNT(mentee_score) AS items_evaluated,
                    SUM(CASE WHEN is_advanced = 0 AND mentee_score >= 4 THEN 1 ELSE 0 END) AS required_achieved,
                    SUM(CASE WHEN mentee_score >= 4 THEN 1 ELSE 0 END) AS total_achieved,
                    SUM(CASE WHEN is_mark = 1 AND is_advanced = 0 THEN 1 ELSE 0 END) AS required_marked,
                    SUM(is_mark) AS total_marked,
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
                COALESCE(tr.required_items, 0) - COALESCE(sa.required_marked, 0) AS required_items,
                COALESCE(tr.total_items, 0) - COALESCE(sa.total_marked, 0) AS total_items,
                COALESCE(sa.required_achieved, 0) AS required_achieved,
                COALESCE(sa.total_achieved, 0) AS total_achieved,
                COALESCE(sa.items_evaluated, 0) AS items_evaluated,
                sa.current_avg_score,
                COALESCE(sa.total_marked, 0) AS not_assessable_items
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
                jp.current_avg_score,
                jp.not_assessable_items
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
                     jp.total_achieved, jp.items_evaluated, jp.current_avg_score, jp.not_assessable_items
            SQL);

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
            ),
            -- Each item's latest entry, a score or a not-assessable mark, by
            -- session and then round. A mark there means not assessable now.
            latest_marks AS (
                SELECT evaluation_group_id, item_id, not_assessable_reason, not_assessable_note
                FROM (
                    SELECT
                        os.evaluation_group_id,
                        sis.item_id,
                        sis.mentee_score,
                        sis.not_assessable_reason,
                        sis.not_assessable_note,
                        ROW_NUMBER() OVER (
                            PARTITION BY os.evaluation_group_id, sis.item_id
                            ORDER BY os.session_number DESC, sis.round_index DESC, sis.id DESC
                        ) AS rn
                    FROM session_item_scores sis
                    JOIN ordered_sessions os ON os.id = sis.session_id
                    WHERE sis.mentee_score IS NOT NULL OR sis.not_assessable_reason IS NOT NULL
                ) ranked
                WHERE rn = 1 AND mentee_score IS NULL
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
                    WHEN lm.item_id IS NOT NULL THEN 'not_assessable'
                    WHEN ls.latest_score >= 4 THEN 'achieved'
                    WHEN ls.latest_score IS NOT NULL THEN 'pending'
                    WHEN COALESCE(st.times_na, 0) > 0 THEN 'only_na'
                    ELSE 'never'
                END AS coverage_status,
                lm.not_assessable_reason,
                lm.not_assessable_note
            FROM journeys j
            JOIN evaluation_items ei ON ei.tool_id = j.tool_id
            LEFT JOIN item_stats st ON st.evaluation_group_id = j.evaluation_group_id AND st.item_id = ei.id
            LEFT JOIN latest_scores ls ON ls.evaluation_group_id = j.evaluation_group_id AND ls.item_id = ei.id
            LEFT JOIN latest_marks lm ON lm.evaluation_group_id = j.evaluation_group_id AND lm.item_id = ei.id
            SQL);
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            DB::statement(<<<'SQL'
                CREATE OR REPLACE VIEW v_session_item_scores AS
                WITH ranked_scores AS (
                    SELECT sis.id, sis.session_id, sis.round_index, sis.item_id, sis.mentee_score, sis.notes,
                        ROW_NUMBER() OVER (
                            PARTITION BY sis.session_id, sis.item_id
                            ORDER BY (sis.mentee_score IS NULL), sis.round_index DESC, sis.id DESC
                        ) AS item_round_rank
                    FROM session_item_scores sis
                )
                SELECT id, session_id, round_index, item_id, mentee_score, notes, item_round_rank
                FROM ranked_scores
                WHERE item_round_rank = 1
                SQL);

            DB::statement(<<<'SQL'
                CREATE OR REPLACE VIEW v_latest_item_scores AS
                WITH ranked AS (
                    SELECT es.evaluation_group_id, es.mentee_id, es.tool_id, es.district_id, es.facility_id,
                        scores.item_id, scores.mentee_score, es.eval_date,
                        ROW_NUMBER() OVER (
                            PARTITION BY es.evaluation_group_id, scores.item_id
                            ORDER BY es.eval_date DESC, COALESCE(es.day_round, 999999) DESC, es.created_at DESC
                        ) AS rn
                    FROM v_session_item_scores scores
                    JOIN evaluation_sessions es ON es.id = scores.session_id
                    WHERE scores.mentee_score IS NOT NULL
                )
                SELECT evaluation_group_id, mentee_id, tool_id, district_id, facility_id, item_id, mentee_score,
                    eval_date AS score_date
                FROM ranked
                WHERE rn = 1
                SQL);

            // Restore the previous definitions of the other views.
            foreach ([
                '2026_10_22_000001_competency_from_latest_item_scores.php',
                '2026_10_23_000001_journey_standing_and_progress_views.php',
                '2026_10_23_000002_create_item_coverage_view.php',
            ] as $file) {
                (require __DIR__.'/'.$file)->up();
            }
        }

        Schema::table('session_item_scores', function (Blueprint $table) {
            $table->dropColumn(['not_assessable_reason', 'not_assessable_note']);
        });
    }
};
