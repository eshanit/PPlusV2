<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Services\ReportQueryService;
use App\Services\ReportScopeService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SessionReportController extends Controller
{
    // Mirrors the PEN-Plus Mentorship Tool's own phase-graduation rule (also
    // used by the journey heatmap's "Phase progress" tile): Initial Intensive
    // requires basic competencies at 3+, Ongoing/Supervision at 4+.
    private const PHASE_TARGET_SCORE = ['initial_intensive' => 3];

    public function __construct(
        private readonly ReportScopeService $scope,
        private readonly ReportQueryService $queries,
    ) {}

    public function __invoke(string $session): Response
    {
        $sessionRow = DB::table('evaluation_sessions as es')
            ->join('users as mentees', 'mentees.id', '=', 'es.mentee_id')
            ->join('users as evaluators', 'evaluators.id', '=', 'es.evaluator_id')
            ->join('tools', 'tools.id', '=', 'es.tool_id')
            ->leftJoin('districts', 'districts.id', '=', 'es.district_id')
            ->leftJoin('facilities', 'facilities.id', '=', 'es.facility_id')
            ->join('v_sessions_numbered as sn', 'sn.id', '=', 'es.id')
            ->whereRaw(...$this->scope->scope('es'))
            ->where('es.id', $session)
            ->select([
                'es.id',
                'es.evaluation_group_id',
                'es.tool_id',
                'es.eval_date',
                'es.phase',
                'es.notes',
                'mentees.firstname as mentee_firstname',
                'mentees.lastname as mentee_lastname',
                'evaluators.firstname as evaluator_firstname',
                'evaluators.lastname as evaluator_lastname',
                'tools.label as tool_label',
                'tools.slug as tool_slug',
                'districts.name as district_name',
                'facilities.name as facility_name',
                'sn.session_number',
                'sn.day_round_number',
                'sn.day_round_count',
            ])
            ->first();

        if (! $sessionRow) {
            abort(404);
        }

        $totalSessions = DB::table('v_sessions_numbered')
            ->where('evaluation_group_id', $sessionRow->evaluation_group_id)
            ->count();

        $journeyStatus = DB::table('v_evaluation_group_status')
            ->where('evaluation_group_id', $sessionRow->evaluation_group_id)
            ->select([
                'basic_competent',
                'fully_competent',
                'basic_competent_at',
                'sessions_to_basic_competence',
                'days_to_basic_competence',
            ])
            ->first();

        $counsellingToolId = DB::table('tools')->where('slug', 'counselling')->value('id');

        $toolScores = DB::table('session_item_scores as sis')
            ->join('evaluation_items as ei', 'ei.id', '=', 'sis.item_id')
            ->where('sis.session_id', $session)
            ->where('ei.tool_id', $sessionRow->tool_id)
            ->orderBy('ei.sort_order')
            ->get(['ei.id as item_id', 'ei.number', 'ei.title', 'ei.is_advanced', 'ei.is_critical', 'sis.mentee_score']);

        $counsellingScores = DB::table('session_item_scores as sis')
            ->join('evaluation_items as ei', 'ei.id', '=', 'sis.item_id')
            ->where('sis.session_id', $session)
            ->where('ei.tool_id', $counsellingToolId)
            ->orderBy('ei.sort_order')
            ->get(['ei.id as item_id', 'ei.number', 'ei.title', 'sis.mentee_score']);

        $prevSession = DB::table('v_sessions_numbered')
            ->where('evaluation_group_id', $sessionRow->evaluation_group_id)
            ->where('session_number', $sessionRow->session_number - 1)
            ->select(['id', 'eval_date', 'day_round_number'])
            ->first();

        $prevSessionId = $prevSession?->id;

        // Same-day rounds share an eval_date — "prev" then means an earlier
        // pass through the tool in the *same visit*, not a separate day's
        // visit, and the two read very differently for an M&E officer.
        $prevSessionInfo = $prevSession ? [
            'date' => $prevSession->eval_date,
            'sameDay' => $prevSession->eval_date === $sessionRow->eval_date,
            'dayRoundNumber' => (int) $prevSession->day_round_number,
        ] : null;

        $prevToolScores = $prevSessionId
            ? DB::table('session_item_scores')->where('session_id', $prevSessionId)->pluck('mentee_score', 'item_id')->all()
            : [];

        $prevCounsellingScores = $prevSessionId
            ? DB::table('session_item_scores as sis')
                ->join('evaluation_items as ei', 'ei.id', '=', 'sis.item_id')
                ->where('sis.session_id', $prevSessionId)
                ->where('ei.tool_id', $counsellingToolId)
                ->pluck('sis.mentee_score', 'sis.item_id')
                ->all()
            : [];

        $items = $toolScores->map(fn (object $r): array => [
            'itemId' => $r->item_id,
            'number' => $r->number,
            'title' => $r->title,
            'isAdvanced' => (bool) $r->is_advanced,
            'isCritical' => (bool) $r->is_critical,
            'score' => $r->mentee_score !== null ? (int) $r->mentee_score : null,
            'prevScore' => isset($prevToolScores[$r->item_id]) ? (int) $prevToolScores[$r->item_id] : null,
            'delta' => ($r->mentee_score !== null && isset($prevToolScores[$r->item_id]))
                ? (int) $r->mentee_score - (int) $prevToolScores[$r->item_id]
                : null,
        ])->all();

        $counsellingItems = $counsellingScores->map(fn (object $r): array => [
            'itemId' => $r->item_id,
            'number' => $r->number,
            'title' => $r->title,
            'isAdvanced' => false,
            'isCritical' => false,
            'score' => $r->mentee_score !== null ? (int) $r->mentee_score : null,
            'prevScore' => isset($prevCounsellingScores[$r->item_id]) ? (int) $prevCounsellingScores[$r->item_id] : null,
            'delta' => ($r->mentee_score !== null && isset($prevCounsellingScores[$r->item_id]))
                ? (int) $r->mentee_score - (int) $prevCounsellingScores[$r->item_id]
                : null,
        ])->all();

        $itemsCollection = collect($items);
        $scored = $itemsCollection->whereNotNull('score')->pluck('score')->sort()->values();

        $distribution = [
            'na' => $itemsCollection->whereNull('score')->count(),
            1 => $itemsCollection->where('score', 1)->count(),
            2 => $itemsCollection->where('score', 2)->count(),
            3 => $itemsCollection->where('score', 3)->count(),
            4 => $itemsCollection->where('score', 4)->count(),
            5 => $itemsCollection->where('score', 5)->count(),
        ];

        $phaseTarget = self::PHASE_TARGET_SCORE[$sessionRow->phase] ?? 4;
        $stats = $this->computeStats($scored, $itemsCollection, $phaseTarget);

        $gaps = $this->queries->getGaps($sessionRow->evaluation_group_id);
        $openGaps = collect($gaps)->reject(fn (array $g): bool => $g['isResolved'])->count();
        $latestSupervisionLevel = collect($gaps)
            ->filter(fn (array $g): bool => $g['supervisionLevel'] !== null)
            ->sortByDesc('identifiedAt')
            ->first()['supervisionLevel'] ?? null;

        if ($prevSessionId) {
            $prevMean = DB::table('v_session_averages')->where('session_id', $prevSessionId)->value('avg_mentee_score');
            $stats['vsPrevSession'] = ($prevMean !== null && $stats['mean'] !== null)
                ? round((float) $stats['mean'] - (float) $prevMean, 2)
                : null;
        }

        $trajectory = DB::table('v_sessions_numbered as sn')
            ->leftJoin('v_session_averages as sa', 'sa.session_id', '=', 'sn.id')
            ->where('sn.evaluation_group_id', $sessionRow->evaluation_group_id)
            ->orderBy('sn.session_number')
            ->get(['sn.id as session_id', 'sn.session_number', 'sn.eval_date', 'sn.day_round_number', 'sn.day_round_count', 'sa.avg_mentee_score'])
            ->map(fn (object $s): array => [
                'sessionId' => $s->session_id,
                'session' => (int) $s->session_number,
                'date' => $s->eval_date,
                'dayRoundNumber' => (int) $s->day_round_number,
                'dayRoundCount' => (int) $s->day_round_count,
                'avgScore' => $s->avg_mentee_score !== null ? round((float) $s->avg_mentee_score, 2) : null,
                'isCurrent' => $s->session_id === $session,
            ])
            ->all();

        return Inertia::render('Reports/SessionReport', [
            'session' => [
                'id' => $sessionRow->id,
                'evaluationGroupId' => $sessionRow->evaluation_group_id,
                'menteeName' => trim("{$sessionRow->mentee_firstname} {$sessionRow->mentee_lastname}"),
                'evaluatorName' => trim("{$sessionRow->evaluator_firstname} {$sessionRow->evaluator_lastname}"),
                'toolLabel' => $sessionRow->tool_label,
                'toolSlug' => $sessionRow->tool_slug,
                'date' => $sessionRow->eval_date,
                'phase' => $sessionRow->phase,
                'notes' => $sessionRow->notes,
                'district' => $sessionRow->district_name,
                'facility' => $sessionRow->facility_name,
                'sessionNumber' => (int) $sessionRow->session_number,
                'totalSessions' => $totalSessions,
                'dayRoundNumber' => (int) $sessionRow->day_round_number,
                'dayRoundCount' => (int) $sessionRow->day_round_count,
            ],
            'items' => $items,
            'counsellingItems' => $counsellingItems,
            'distribution' => $distribution,
            'stats' => $stats,
            'prevSession' => $prevSessionInfo,
            'openGaps' => $openGaps,
            'latestSupervisionLevel' => $latestSupervisionLevel,
            'trajectory' => $trajectory,
            'journeyStatus' => $journeyStatus ? [
                'basicCompetent' => (bool) $journeyStatus->basic_competent,
                'fullyCompetent' => (bool) $journeyStatus->fully_competent,
                'basicCompetentAt' => $journeyStatus->basic_competent_at,
                'sessionsToBasic' => $journeyStatus->sessions_to_basic_competence,
                'daysToBasic' => $journeyStatus->days_to_basic_competence,
            ] : null,
        ]);
    }

    /**
     * @param  Collection<int, int>  $scored  sorted scored values (all items, incl. advanced — mean/median/mode
     *                                        describe the whole session, not just the phase-graduation rule)
     * @param  Collection<int, array<string, mixed>>  $allItems  full items collection
     * @param  int  $target  score a basic competency must reach for the mentee's current phase — 3 for Initial
     *                       Intensive, 4 for Ongoing/Supervision, per the tool's own phase-graduation rule
     * @return array<string, float|int|string|null>
     */
    private function computeStats(Collection $scored, Collection $allItems, int $target): array
    {
        $basicScored = $allItems->filter(fn ($i) => ! $i['isAdvanced'] && $i['score'] !== null);
        $atTarget = $basicScored->filter(fn ($i) => $i['score'] >= $target)->count();
        $pct = $basicScored->count() > 0 ? round(($atTarget / $basicScored->count()) * 100, 1) : 0.0;

        if ($scored->isEmpty()) {
            return [
                'mean' => null,
                'median' => null,
                'mode' => null,
                'target' => $target,
                'pctAtCompetency' => $pct,
                'competencyGap' => $basicScored->count() - $atTarget,
                'basicScoredCount' => $basicScored->count(),
                'vsPrevSession' => null,
            ];
        }

        $count = $scored->count();
        $mean = round((float) $scored->avg(), 2);

        $sorted = $scored->values();
        $median = $count % 2 === 0
            ? round(($sorted[$count / 2 - 1] + $sorted[$count / 2]) / 2, 2)
            : (float) $sorted[intdiv($count, 2)];

        $freq = $scored->countBy()->sortDesc();
        $maxFreq = $freq->first();
        $mode = $freq->filter(fn ($f) => $f === $maxFreq)->keys()->implode(', ');

        return [
            'mean' => $mean,
            'median' => $median,
            'mode' => $mode,
            'target' => $target,
            'pctAtCompetency' => $pct,
            'competencyGap' => $basicScored->count() - $atTarget,
            'basicScoredCount' => $basicScored->count(),
            'vsPrevSession' => null,
        ];
    }
}
