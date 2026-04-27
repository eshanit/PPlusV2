<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Services\ReportScopeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class JourneyGapsController extends Controller
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
                'open_gaps',
                'resolved_gaps',
            ])
            ->first();

        if (! $journey) {
            abort(404);
        }

        $gaps = DB::table('gap_entries')
            ->where('evaluation_group_id', $groupId)
            ->orderBy('identified_at')
            ->get([
                'id',
                'identified_at',
                'description',
                'domains',
                'covered_in_mentorship',
                'covering_later',
                'timeline',
                'supervision_level',
                'resolution_note',
                'resolved_at',
            ])
            ->map(fn (object $g): array => [
                'id' => $g->id,
                'identifiedAt' => $g->identified_at,
                'description' => $g->description,
                'domains' => json_decode($g->domains, true) ?? [],
                'coveredInMentorship' => $g->covered_in_mentorship,
                'coveringLater' => (bool) $g->covering_later,
                'timeline' => $g->timeline,
                'supervisionLevel' => $g->supervision_level,
                'resolutionNote' => $g->resolution_note,
                'resolvedAt' => $g->resolved_at,
                'isResolved' => $g->resolved_at !== null,
            ])
            ->all();

        return Inertia::render('Reports/JourneyGaps', [
            'journey' => [
                'groupId' => $journey->evaluation_group_id,
                'menteeName' => trim("{$journey->mentee_firstname} {$journey->mentee_lastname}"),
                'evaluatorName' => trim("{$journey->evaluator_firstname} {$journey->evaluator_lastname}"),
                'toolLabel' => $journey->tool_label,
                'district' => $journey->district_name,
                'facility' => $journey->facility_name,
                'status' => $journey->competency_status,
                'totalSessions' => (int) $journey->total_sessions,
                'openGaps' => (int) $journey->open_gaps,
                'resolvedGaps' => (int) $journey->resolved_gaps,
            ],
            'gaps' => $gaps,
        ]);
    }
}
