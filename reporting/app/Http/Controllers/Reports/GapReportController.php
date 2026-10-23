<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\GapEntry;
use App\Services\ReportQueryService;
use App\Services\ReportScopeService;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class GapReportController extends Controller
{
    private const DOMAINS = [
        'knowledge' => 'Knowledge',
        'critical_reasoning' => 'Critical Reasoning',
        'clinical_skills' => 'Clinical Skills',
        'communication' => 'Communication',
        'attitude' => 'Attitude',
    ];

    private const SUPERVISION_LEVELS = [
        'intensive_mentorship' => 'Intensive Mentorship',
        'ongoing_mentorship' => 'Ongoing Mentorship',
        'independent_practice' => 'Independent Practice',
    ];

    public function __construct(private readonly ReportScopeService $scope) {}

    public function __invoke(string $id): Response
    {
        $gap = GapEntry::with(['mentee:id,firstname,lastname', 'evaluator:id,firstname,lastname', 'tool:id,label,slug'])
            ->findOrFail($id);

        $journey = DB::table('v_journey_summary')
            ->whereRaw(...$this->scope->scope('v_journey_summary'))
            ->where('evaluation_group_id', $gap->evaluation_group_id)
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
            abort(403);
        }

        $trajectory = DB::table('v_sessions_numbered as sn')
            ->leftJoin('v_session_averages as sa', 'sa.session_id', '=', 'sn.id')
            ->leftJoin('v_journey_progress as jp', 'jp.session_id', '=', 'sn.id')
            ->where('sn.evaluation_group_id', $gap->evaluation_group_id)
            ->orderBy('sn.session_number')
            ->get([
                'sn.id as session_id',
                'sn.session_number',
                'sn.eval_date',
                'sa.avg_mentee_score',
                'jp.required_items', 'jp.required_achieved', 'jp.total_items', 'jp.total_achieved', 'jp.items_evaluated', 'jp.current_avg_score',
            ])
            ->map(fn (object $s): array => [
                ...ReportQueryService::standing($s),
                'sessionId' => $s->session_id,
                'sessionNumber' => (int) $s->session_number,
                'date' => $s->eval_date,
                'avgScore' => $s->avg_mentee_score !== null ? round((float) $s->avg_mentee_score, 2) : null,
                'afterGap' => $s->eval_date > $gap->identified_at->toDateString(),
            ])
            ->all();

        $sessionsAtIdentification = DB::table('v_sessions_numbered')
            ->where('evaluation_group_id', $gap->evaluation_group_id)
            ->where('eval_date', '<=', $gap->identified_at->toDateString())
            ->count();

        // Standing (latest score per item) when the gap was identified, and now.
        // Averages of session averages would compare different sets of items.
        $trajectoryRows = collect($trajectory);
        $standingBefore = $trajectoryRows->filter(fn (array $s): bool => ! $s['afterGap'])->last();
        $standingNow = $trajectoryRows->filter(fn (array $s): bool => $s['afterGap'])->last();
        $avgBefore = $standingBefore['currentAvgScore'] ?? null;
        $avgAfter = $standingNow['currentAvgScore'] ?? null;

        $daysOpen = $gap->resolved_at
            ? $gap->identified_at->diffInDays($gap->resolved_at)
            : $gap->identified_at->diffInDays(now());

        return Inertia::render('Reports/GapReport', [
            'gap' => [
                'id' => $gap->id,
                'identifiedAt' => $gap->identified_at->toDateString(),
                'sessionRound' => $gap->session_round,
                'description' => $gap->description,
                'domains' => $gap->domains ?? [],
                'coveredInMentorship' => $gap->covered_in_mentorship,
                'coveringLater' => $gap->covering_later,
                'timeline' => $gap->timeline,
                'supervisionLevel' => $gap->supervision_level,
                'resolutionNote' => $gap->resolution_note,
                'resolvedAt' => $gap->resolved_at?->toDateString(),
                'isResolved' => $gap->resolved_at !== null,
                'daysOpen' => (int) $daysOpen,
            ],
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
            'stats' => [
                'sessionsAtIdentification' => $sessionsAtIdentification,
                'totalSessions' => (int) $journey->total_sessions,
                'sessionsAfter' => max(0, (int) $journey->total_sessions - $sessionsAtIdentification),
                'avgScoreBefore' => $avgBefore !== null ? round((float) $avgBefore, 2) : null,
                'avgScoreAfter' => $avgAfter !== null ? round((float) $avgAfter, 2) : null,
                'achievedBefore' => $standingBefore ? "{$standingBefore['requiredAchieved']} of {$standingBefore['requiredItems']}" : null,
                'achievedNow' => $standingNow ? "{$standingNow['requiredAchieved']} of {$standingNow['requiredItems']}" : null,
            ],
            'trajectory' => $trajectory,
            'domainLabels' => self::DOMAINS,
            'supervisionLabels' => self::SUPERVISION_LEVELS,
        ]);
    }
}
