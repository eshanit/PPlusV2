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

        DB::statement("
            CREATE OR REPLACE VIEW v_evaluation_group_status AS
            WITH numbered_sessions AS (
                SELECT
                    s.*,
                    ROW_NUMBER() OVER (
                        PARTITION BY s.evaluation_group_id
                        ORDER BY s.eval_date, s.created_at
                    ) AS session_number
                FROM evaluation_sessions s
            ),
            tool_requirements AS (
                SELECT
                    ei.tool_id,
                    COUNT(*) AS total_items,
                    SUM(CASE WHEN ei.is_advanced = 0 THEN 1 ELSE 0 END) AS basic_items
                FROM evaluation_items ei
                JOIN tools t ON t.id = ei.tool_id
                WHERE t.slug != 'counselling'
                GROUP BY ei.tool_id
            ),
            session_competency AS (
                SELECT
                    ns.id AS session_id,
                    ns.evaluation_group_id,
                    ns.mentee_id,
                    ns.tool_id,
                    ns.eval_date,
                    ns.created_at,
                    ns.session_number,
                    tr.total_items,
                    tr.basic_items,
                    SUM(
                        CASE
                            WHEN ei.is_advanced = 0 AND sis.mentee_score IN (4, 5)
                                THEN 1
                            ELSE 0
                        END
                    ) AS basic_competent_items,
                    SUM(
                        CASE
                            WHEN sis.mentee_score IN (4, 5)
                                THEN 1
                            ELSE 0
                        END
                    ) AS fully_competent_items
                FROM numbered_sessions ns
                JOIN tool_requirements tr ON tr.tool_id = ns.tool_id
                JOIN evaluation_items ei ON ei.tool_id = ns.tool_id
                LEFT JOIN session_item_scores sis
                    ON sis.session_id = ns.id
                    AND sis.item_id = ei.id
                GROUP BY
                    ns.id,
                    ns.evaluation_group_id,
                    ns.mentee_id,
                    ns.tool_id,
                    ns.eval_date,
                    ns.created_at,
                    ns.session_number,
                    tr.total_items,
                    tr.basic_items
            ),
            group_status AS (
                SELECT
                    evaluation_group_id,
                    mentee_id,
                    tool_id,
                    COUNT(*) AS total_sessions,
                    MIN(eval_date) AS first_session_date,
                    MAX(eval_date) AS latest_session_date,
                    MAX(session_number) AS latest_session_number,
                    MIN(
                        CASE
                            WHEN basic_competent_items = basic_items
                                THEN session_number
                        END
                    ) AS sessions_to_basic_competence,
                    MIN(
                        CASE
                            WHEN basic_competent_items = basic_items
                                THEN eval_date
                        END
                    ) AS basic_competent_at,
                    MIN(
                        CASE
                            WHEN fully_competent_items = total_items
                                THEN session_number
                        END
                    ) AS sessions_to_full_competence,
                    MIN(
                        CASE
                            WHEN fully_competent_items = total_items
                                THEN eval_date
                        END
                    ) AS full_competent_at
                FROM session_competency
                GROUP BY evaluation_group_id, mentee_id, tool_id
            )
            SELECT
                gs.evaluation_group_id,
                gs.mentee_id,
                gs.tool_id,
                latest.district_id,
                latest.facility_id,
                gs.total_sessions,
                gs.first_session_date,
                gs.latest_session_date,
                gs.latest_session_number,
                gs.sessions_to_basic_competence,
                gs.basic_competent_at,
                DATEDIFF(gs.basic_competent_at, gs.first_session_date) AS days_to_basic_competence,
                gs.sessions_to_full_competence,
                gs.full_competent_at,
                DATEDIFF(gs.full_competent_at, gs.first_session_date) AS days_to_full_competence,
                gs.sessions_to_basic_competence IS NOT NULL AS basic_competent,
                gs.sessions_to_full_competence IS NOT NULL AS fully_competent,
                CASE
                    WHEN gs.sessions_to_basic_competence IS NOT NULL THEN 'complete'
                    ELSE 'in_progress'
                END AS status
            FROM group_status gs
            JOIN numbered_sessions latest
                ON latest.evaluation_group_id = gs.evaluation_group_id
                AND latest.session_number = gs.latest_session_number
        ");
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS v_evaluation_group_status');
        DB::statement('DROP VIEW IF EXISTS v_session_averages');
        DB::statement('DROP VIEW IF EXISTS v_sessions_numbered');
    }
};
