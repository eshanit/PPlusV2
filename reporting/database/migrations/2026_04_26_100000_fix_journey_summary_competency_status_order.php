<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Fix: fully_competent must be checked before basic_competent in the CASE expression.
     * A journey meeting full competency also meets basic competency, so basic_competent always
     * matched first in the original view, making "fully_competent" unreachable.
     */
    public function up(): void
    {
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
        ');
    }

    public function down(): void
    {
        // Restore original (broken) CASE order — use only if rolling back to test
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
                vgs.basic_competent_at,
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
                SELECT evaluation_group_id, COUNT(*) AS open_gaps
                FROM gap_entries WHERE resolved_at IS NULL GROUP BY evaluation_group_id
            ) ge_open ON ge_open.evaluation_group_id = vgs.evaluation_group_id
            LEFT JOIN (
                SELECT evaluation_group_id, COUNT(*) AS resolved_gaps
                FROM gap_entries WHERE resolved_at IS NOT NULL GROUP BY evaluation_group_id
            ) ge_resolved ON ge_resolved.evaluation_group_id = vgs.evaluation_group_id
            GROUP BY vgs.evaluation_group_id, vgs.mentee_id, vgs.evaluator_id, vgs.tool_id,
                     vgs.district_id, vgs.facility_id, vgs.latest_session_id, vgs.latest_session_date,
                     vgs.latest_phase, vgs.basic_competent, vgs.fully_competent, vgs.basic_competent_at,
                     vgs.sessions_to_basic_competence, vgs.days_to_basic_competence, vgs.first_full_competency_date,
                     vgs.sessions_to_full_competence, vgs.days_to_full_competence, vgs.total_sessions,
                     t.label, t.slug, d.name, f.name, u.firstname, u.lastname, u.email,
                     ev.firstname, ev.lastname, ge_open.open_gaps, ge_resolved.resolved_gaps,
                     vgs.last_updated
        ');
    }
};
