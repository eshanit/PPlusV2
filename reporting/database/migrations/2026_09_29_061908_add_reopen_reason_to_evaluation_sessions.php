<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Supports reopening a journey that closed on a score given in error.
 *
 * The monitoring app records the mentor's reason on the first session after a
 * reopen. v_evaluation_group_status previously treated a journey as competent
 * if *any* session ever was, and dated competence from the *first* such
 * session — so a reopened journey would still report the mistaken competence.
 * Competency is now only counted from the latest reopened session onwards.
 * Journeys that were never reopened produce exactly the same output as before.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evaluation_sessions', function (Blueprint $table) {
            $table->text('reopen_reason')->nullable()->after('notes');
        });

        $this->recreateStatusView();
    }

    /**
     * Public so a later migration's down() can restore this version of the view.
     */
    public function recreateStatusView(): void
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
                sc.evaluator_id,
                sc.tool_id,
                sc.district_id,
                sc.facility_id,
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
            GROUP BY sc.evaluation_group_id, sc.mentee_id, sc.evaluator_id, sc.tool_id,
                     sc.district_id, sc.facility_id
        ');
    }

    public function down(): void
    {
        // Restore the previous view definition before dropping the column it now depends on.
        (require database_path('migrations/2026_07_29_000000_fix_evaluation_group_status_only_full_group_by.php'))->up();

        Schema::table('evaluation_sessions', function (Blueprint $table) {
            $table->dropColumn('reopen_reason');
        });
    }
};
