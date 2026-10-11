<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evaluation_sessions', function (Blueprint $table) {
            $table->unsignedSmallInteger('round_count')->default(1)->after('day_round');
            $table->json('selected_item_slugs')->nullable()->after('round_count');
            $table->json('selected_counselling_slugs')->nullable()->after('selected_item_slugs');
        });

        Schema::table('session_item_scores', function (Blueprint $table) {
            $table->unsignedSmallInteger('round_index')->default(1)->after('session_id');
            $table->dropUnique('uq_score_session_item');
            $table->unique(['session_id', 'item_id', 'round_index'], 'uq_score_session_item_round');
        });

        if (DB::connection()->getDriverName() === 'sqlite') {
            $this->createSqliteSessionItemScoresView();
        } else {
            $this->replaceSessionViews();
        }
    }

    public function down(): void
    {
        $hasMultipleRounds = DB::table('session_item_scores')
            ->select('session_id', 'item_id')
            ->groupBy('session_id', 'item_id')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($hasMultipleRounds) {
            throw new RuntimeException('Cannot roll back round-aware scores while sessions contain repeated item scores.');
        }

        if (DB::connection()->getDriverName() !== 'sqlite') {
            DB::statement('DROP VIEW IF EXISTS v_day_progress');
            DB::statement('DROP VIEW IF EXISTS v_evaluation_group_status');
            DB::statement('DROP VIEW IF EXISTS v_latest_item_scores');
            DB::statement('DROP VIEW IF EXISTS v_session_averages');
            DB::statement('DROP VIEW IF EXISTS v_session_item_scores');
            DB::statement('DROP VIEW IF EXISTS v_sessions_numbered');
            $this->restoreLegacySessionAverageView();
        } else {
            DB::statement('DROP VIEW IF EXISTS v_session_item_scores');
        }

        Schema::table('session_item_scores', function (Blueprint $table) {
            $table->dropUnique('uq_score_session_item_round');
            $table->unique(['session_id', 'item_id'], 'uq_score_session_item');
            $table->dropColumn('round_index');
        });

        Schema::table('evaluation_sessions', function (Blueprint $table) {
            $table->dropColumn(['round_count', 'selected_item_slugs', 'selected_counselling_slugs']);
        });

        if (DB::connection()->getDriverName() !== 'sqlite') {
            (require database_path('migrations/2026_09_29_064717_keep_journey_status_single_row_across_evaluators.php'))->up();
            (require database_path('migrations/2026_09_16_090224_add_day_progress_views.php'))->up();
            (require database_path('migrations/2026_09_16_102211_add_day_round_to_evaluation_sessions.php'))->up();
        }
    }

    private function replaceSessionViews(): void
    {
        DB::statement('
            CREATE OR REPLACE VIEW v_session_item_scores AS
            WITH ranked_scores AS (
                SELECT
                    sis.*,
                    ROW_NUMBER() OVER (
                        PARTITION BY sis.session_id, sis.item_id
                        ORDER BY
                            (sis.mentee_score IS NULL),
                            sis.round_index DESC,
                            sis.id DESC
                    ) AS item_round_rank
                FROM session_item_scores sis
            )
            SELECT *
            FROM ranked_scores
            WHERE item_round_rank = 1
        ');

        DB::statement('
            CREATE OR REPLACE VIEW v_latest_item_scores AS
            WITH ranked AS (
                SELECT
                    es.evaluation_group_id,
                    es.mentee_id,
                    es.tool_id,
                    es.district_id,
                    es.facility_id,
                    scores.item_id,
                    scores.mentee_score,
                    es.eval_date,
                    ROW_NUMBER() OVER (
                        PARTITION BY es.evaluation_group_id, scores.item_id
                        ORDER BY
                            es.eval_date DESC,
                            COALESCE(es.day_round, 999999) DESC,
                            es.created_at DESC
                    ) AS rn
                FROM v_session_item_scores scores
                JOIN evaluation_sessions es ON es.id = scores.session_id
                WHERE scores.mentee_score IS NOT NULL
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

        DB::statement('
            CREATE OR REPLACE VIEW v_sessions_numbered AS
            SELECT
                s.*,
                ROW_NUMBER() OVER (
                    PARTITION BY s.evaluation_group_id
                    ORDER BY s.eval_date, s.created_at
                ) AS session_number,
                ROW_NUMBER() OVER (
                    PARTITION BY s.evaluation_group_id, s.eval_date
                    ORDER BY COALESCE(s.day_round, 999999), s.created_at
                ) AS day_round_number,
                COUNT(*) OVER (
                    PARTITION BY s.evaluation_group_id, s.eval_date
                ) AS day_round_count
            FROM evaluation_sessions s
        ');

        DB::statement('
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
                COUNT(sis.id) AS scored_items,
                SUM(CASE WHEN sis.mentee_score IS NULL THEN 1 ELSE 0 END) AS na_items
            FROM v_session_item_scores sis
            JOIN evaluation_sessions es ON es.id = sis.session_id
            JOIN evaluation_items ei ON ei.id = sis.item_id
            JOIN tools t ON t.id = ei.tool_id AND t.slug != "counselling"
            GROUP BY
                sis.session_id, es.mentee_id, es.evaluator_id,
                es.tool_id, es.eval_date, es.district_id,
                es.facility_id, es.phase
        ');

        DB::statement('
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
        ');

        DB::statement('
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
                    current_session.session_id,
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
        ');
    }

    private function createSqliteSessionItemScoresView(): void
    {
        DB::statement('
            CREATE VIEW v_session_item_scores AS
            SELECT sis.*
            FROM session_item_scores sis
            WHERE NOT EXISTS (
                SELECT 1
                FROM session_item_scores candidate
                WHERE candidate.session_id = sis.session_id
                    AND candidate.item_id = sis.item_id
                    AND (
                        CASE WHEN candidate.mentee_score IS NULL THEN 1 ELSE 0 END
                            < CASE WHEN sis.mentee_score IS NULL THEN 1 ELSE 0 END
                        OR (
                            (candidate.mentee_score IS NULL) = (sis.mentee_score IS NULL)
                            AND candidate.round_index > sis.round_index
                        )
                        OR (
                            (candidate.mentee_score IS NULL) = (sis.mentee_score IS NULL)
                            AND candidate.round_index = sis.round_index
                            AND candidate.id > sis.id
                        )
                    )
            )
        ');
    }

    private function restoreLegacySessionAverageView(): void
    {
        DB::statement('
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
                COUNT(sis.id) AS scored_items,
                SUM(CASE WHEN sis.mentee_score IS NULL THEN 1 ELSE 0 END) AS na_items
            FROM session_item_scores sis
            JOIN evaluation_sessions es ON es.id = sis.session_id
            JOIN evaluation_items ei ON ei.id = sis.item_id
            JOIN tools t ON t.id = ei.tool_id AND t.slug != "counselling"
            GROUP BY
                sis.session_id, es.mentee_id, es.evaluator_id,
                es.tool_id, es.eval_date, es.district_id,
                es.facility_id, es.phase
        ');
    }
};
