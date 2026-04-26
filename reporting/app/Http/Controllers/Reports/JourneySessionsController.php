<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Services\ReportScopeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class JourneySessionsController extends Controller
{
    public function __construct(private readonly ReportScopeService $scope) {}

    public function __invoke(Request $request): Response
    {
        $groupId = $request->input('group_id');

        if (! $groupId) {
            abort(404);
        }

        $journey = DB::table('v_journey_summary')
            ->whereRaw(...$this->scope->scope('v_journey_summary'))
            ->where('evaluation_group_id', $groupId)
            ->select([
                'evaluation_group_id',
                'mentee_firstname',
                'mentee_lastname',
                'evaluator_firstname',
                'evaluator_lastname',
                'tool_label',
                'district_name',
                'facility_name',
                'competency_status',
                'total_sessions',
                'basic_competent_at',
                'sessions_to_basic_competence',
                'days_to_basic_competence',
                'open_gaps',
                'resolved_gaps',
            ])
            ->first();

        if (! $journey) {
            abort(404);
        }

        $sessions = DB::table('v_sessions_numbered as sn')
            ->leftJoin('v_session_averages as sa', 'sa.session_id', '=', 'sn.id')
            ->where('sn.evaluation_group_id', $groupId)
            ->orderBy('sn.session_number')
            ->get([
                'sn.id as session_id',
                'sn.session_number',
                'sn.eval_date',
                'sn.phase',
                'sa.avg_mentee_score',
                'sa.scored_items',
                'sa.na_items',
            ])
            ->map(fn (object $s): array => [
                'sessionId' => $s->session_id,
                'sessionNumber' => (int) $s->session_number,
                'date' => $s->eval_date,
                'phase' => $s->phase,
                'avgScore' => $s->avg_mentee_score !== null ? round((float) $s->avg_mentee_score, 2) : null,
                'scoredItems' => $s->scored_items !== null ? (int) $s->scored_items : null,
                'naItems' => $s->na_items !== null ? (int) $s->na_items : null,
            ])
            ->all();

        return Inertia::render('Reports/JourneySessions', [
            'journey' => [
                'groupId' => $journey->evaluation_group_id,
                'menteeName' => trim("{$journey->mentee_firstname} {$journey->mentee_lastname}"),
                'evaluatorName' => trim("{$journey->evaluator_firstname} {$journey->evaluator_lastname}"),
                'toolLabel' => $journey->tool_label,
                'district' => $journey->district_name,
                'facility' => $journey->facility_name,
                'status' => $journey->competency_status,
                'totalSessions' => (int) $journey->total_sessions,
                'basicCompetentAt' => $journey->basic_competent_at,
                'sessionsToBasic' => $journey->sessions_to_basic_competence,
                'daysToBasic' => $journey->days_to_basic_competence,
                'openGaps' => (int) $journey->open_gaps,
                'resolvedGaps' => (int) $journey->resolved_gaps,
            ],
            'sessions' => $sessions,
        ]);
    }
}
