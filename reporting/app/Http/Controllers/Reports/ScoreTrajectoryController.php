<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ScoreTrajectoryController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $toolId = $request->input('tool_id');
        $groupId = $request->input('group_id');

        $tools = DB::table('tools')
            ->where('slug', '!=', 'counselling')
            ->orderBy('sort_order')
            ->get(['id', 'label'])
            ->map(fn ($t) => ['id' => (int) $t->id, 'label' => $t->label])
            ->all();

        $journeys = [];
        $trajectory = [];
        $selectedJourney = null;

        if ($toolId) {
            $journeys = DB::table('v_journey_summary')
                ->where('tool_id', $toolId)
                ->orderBy('mentee_lastname')
                ->orderBy('mentee_firstname')
                ->get(['evaluation_group_id', 'mentee_firstname', 'mentee_lastname', 'total_sessions', 'competency_status'])
                ->map(fn (object $j): array => [
                    'groupId' => $j->evaluation_group_id,
                    'label' => trim("{$j->mentee_firstname} {$j->mentee_lastname}")." ({$j->total_sessions} sessions)",
                    'status' => $j->competency_status,
                ])
                ->all();
        }

        if ($groupId) {
            $summary = DB::table('v_journey_summary')
                ->where('evaluation_group_id', $groupId)
                ->first();

            if ($summary) {
                $selectedJourney = [
                    'groupId' => $summary->evaluation_group_id,
                    'mentee' => trim("{$summary->mentee_firstname} {$summary->mentee_lastname}"),
                    'tool' => $summary->tool_label,
                    'status' => $summary->competency_status,
                    'totalSessions' => (int) $summary->total_sessions,
                    'basicCompetentAt' => $summary->basic_competent_at,
                    'sessionsToBasic' => $summary->sessions_to_basic_competence,
                ];
            }

            $trajectory = DB::table('v_sessions_numbered as sn')
                ->join('v_session_averages as sa', 'sa.session_id', '=', 'sn.id')
                ->where('sn.evaluation_group_id', $groupId)
                ->orderBy('sn.session_number')
                ->get([
                    'sn.session_number',
                    'sn.eval_date',
                    'sn.phase',
                    'sa.avg_mentee_score',
                    'sa.scored_items',
                ])
                ->map(fn (object $s): array => [
                    'session' => (int) $s->session_number,
                    'date' => $s->eval_date,
                    'phase' => $s->phase,
                    'avgScore' => $s->avg_mentee_score !== null ? round((float) $s->avg_mentee_score, 2) : null,
                    'scoredItems' => (int) $s->scored_items,
                ])
                ->all();
        }

        return Inertia::render('Reports/ScoreTrajectory', [
            'tools' => $tools,
            'journeys' => $journeys,
            'trajectory' => $trajectory,
            'selectedJourney' => $selectedJourney,
            'filters' => ['tool_id' => $toolId, 'group_id' => $groupId],
        ]);
    }
}
