<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\EvaluationItem;
use App\Services\ReportScopeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ItemAnalysisController extends Controller
{
    public function __construct(private readonly ReportScopeService $scope) {}

    public function __invoke(Request $request, int $id): Response
    {
        $item = EvaluationItem::with(['tool', 'category'])->findOrFail($id);
        $menteeId = $request->input('mentee_id');

        // Summary stats
        $statsRow = DB::table('v_session_item_scores as sis')
            ->join('evaluation_sessions as es', 'es.id', '=', 'sis.session_id')
            ->where('sis.item_id', $id)
            ->whereRaw(...$this->scope->scope('es'))
            ->when($menteeId, fn ($q) => $q->where('es.mentee_id', $menteeId))
            ->selectRaw('COUNT(sis.mentee_score) as times_scored')
            ->selectRaw('ROUND(AVG(sis.mentee_score), 2) as avg_score')
            ->selectRaw('SUM(CASE WHEN sis.mentee_score >= 4 THEN 1 ELSE 0 END) as count_competent')
            ->selectRaw('COUNT(DISTINCT es.evaluation_group_id) as journey_count')
            ->first();

        $timesScored = (int) ($statsRow->times_scored ?? 0);

        // Coverage. Mentors evaluate only what they observed, so some items come
        // up far less often than others; these show how often this one is reached.
        $toolCoverage = DB::table('evaluation_sessions as es')
            ->where('es.tool_id', $item->tool_id)
            ->whereRaw(...$this->scope->scope('es'))
            ->when($menteeId, fn ($q) => $q->where('es.mentee_id', $menteeId))
            ->selectRaw('COUNT(DISTINCT es.evaluation_group_id) as tool_journeys')
            ->selectRaw('COUNT(DISTINCT es.id) as tool_sessions')
            ->first();
        $itemCoverage = DB::table('v_session_item_scores as sis')
            ->join('evaluation_sessions as es', 'es.id', '=', 'sis.session_id')
            ->where('sis.item_id', $id)
            ->whereNotNull('sis.mentee_score')
            ->whereRaw(...$this->scope->scope('es'))
            ->when($menteeId, fn ($q) => $q->where('es.mentee_id', $menteeId))
            ->selectRaw('COUNT(DISTINCT es.evaluation_group_id) as journeys_evaluated')
            ->selectRaw('COUNT(DISTINCT es.id) as sessions_evaluated')
            ->first();
        $toolJourneys = (int) ($toolCoverage->tool_journeys ?? 0);
        $toolSessions = (int) ($toolCoverage->tool_sessions ?? 0);
        $journeysEvaluated = (int) ($itemCoverage->journeys_evaluated ?? 0);
        $sessionsEvaluated = (int) ($itemCoverage->sessions_evaluated ?? 0);
        $journeysAchieved = DB::table('v_latest_item_scores as vlis')
            ->where('vlis.item_id', $id)
            ->where('vlis.mentee_score', '>=', 4)
            ->whereRaw(...$this->scope->scope('vlis'))
            ->when($menteeId, fn ($q) => $q->where('vlis.mentee_id', $menteeId))
            ->count();

        $stats = [
            'toolJourneys' => $toolJourneys,
            'journeysEvaluated' => $journeysEvaluated,
            'journeysNeverEvaluated' => max(0, $toolJourneys - $journeysEvaluated),
            'journeysAchieved' => $journeysAchieved,
            'journeyCoveragePct' => $toolJourneys > 0 ? (int) round($journeysEvaluated / $toolJourneys * 100) : null,
            'sessionCoveragePct' => $toolSessions > 0 ? (int) round($sessionsEvaluated / $toolSessions * 100) : null,
            'avgScore' => $statsRow->avg_score !== null ? (float) $statsRow->avg_score : null,
            'pctCompetent' => $timesScored > 0
                ? round(((int) $statsRow->count_competent / $timesScored) * 100, 1)
                : null,
            'timesScored' => $timesScored,
            'journeyCount' => (int) ($statsRow->journey_count ?? 0),
        ];

        // Score distribution (how many times each score 1-5 was given)
        $distRows = DB::table('v_session_item_scores as sis')
            ->join('evaluation_sessions as es', 'es.id', '=', 'sis.session_id')
            ->where('sis.item_id', $id)
            ->whereNotNull('sis.mentee_score')
            ->whereRaw(...$this->scope->scope('es'))
            ->when($menteeId, fn ($q) => $q->where('es.mentee_id', $menteeId))
            ->selectRaw('sis.mentee_score as score, COUNT(*) as cnt')
            ->groupBy('sis.mentee_score')
            ->orderBy('sis.mentee_score')
            ->get();

        // Ensure all scores 1-5 are present (fill missing with 0)
        $distributionMap = $distRows->pluck('cnt', 'score')->all();
        $distribution = collect([1, 2, 3, 4, 5])->map(fn ($s) => [
            'score' => $s,
            'count' => (int) ($distributionMap[$s] ?? 0),
        ])->all();

        // Score trend by session number (avg score across all journeys at session N)
        // Only include session numbers where ≥ 3 journeys contributed
        $trend = DB::table('v_session_item_scores as sis')
            ->join('v_sessions_numbered as vsn', 'vsn.id', '=', 'sis.session_id')
            ->where('sis.item_id', $id)
            ->whereNotNull('sis.mentee_score')
            ->whereRaw(...$this->scope->scope('vsn'))
            ->when($menteeId, fn ($q) => $q->where('vsn.mentee_id', $menteeId))
            ->selectRaw('vsn.session_number, ROUND(AVG(sis.mentee_score), 2) as avg_score, COUNT(*) as journey_count')
            ->groupBy('vsn.session_number')
            ->havingRaw('COUNT(*) >= 3')
            ->orderBy('vsn.session_number')
            ->get()
            ->map(fn (object $row): array => [
                'sessionNumber' => (int) $row->session_number,
                'avgScore' => (float) $row->avg_score,
                'journeyCount' => (int) $row->journey_count,
            ])
            ->all();

        // Journey breakdown: one row per journey, latest score for this item
        $journeys = DB::table('v_latest_item_scores as vlis')
            ->join('users as u', 'u.id', '=', 'vlis.mentee_id')
            ->leftJoin('facilities as f', 'f.id', '=', 'vlis.facility_id')
            ->where('vlis.item_id', $id)
            ->whereNotNull('vlis.mentee_score')
            ->whereRaw(...$this->scope->scope('vlis'))
            ->when($menteeId, fn ($q) => $q->where('vlis.mentee_id', $menteeId))
            ->select([
                'vlis.evaluation_group_id',
                'u.firstname',
                'u.lastname',
                'f.name as facility',
                'vlis.mentee_score as latest_score',
                'vlis.score_date',
            ])
            ->selectRaw(
                '(SELECT COUNT(sis3.id) FROM v_session_item_scores sis3
                  INNER JOIN evaluation_sessions es3 ON es3.id = sis3.session_id
                  WHERE sis3.item_id = ? AND es3.evaluation_group_id = vlis.evaluation_group_id
                    AND sis3.mentee_score IS NOT NULL) as times_scored',
                [$id]
            )
            ->orderBy('vlis.mentee_score')
            ->get()
            ->map(fn (object $row): array => [
                'evaluationGroupId' => $row->evaluation_group_id,
                'mentee' => trim("{$row->firstname} {$row->lastname}"),
                'facility' => $row->facility,
                'latestScore' => (int) $row->latest_score,
                'scoreDate' => $row->score_date,
                'timesScored' => (int) $row->times_scored,
            ])
            ->all();

        return Inertia::render('Reports/ItemAnalysis', [
            'item' => [
                'id' => $item->id,
                'slug' => $item->slug,
                'number' => $item->number,
                'title' => $item->title,
                'isAdvanced' => $item->is_advanced,
                'tool' => ['id' => $item->tool->id, 'slug' => $item->tool->slug, 'label' => $item->tool->label],
                'category' => $item->category?->name ?? 'Uncategorised',
            ],
            'stats' => $stats,
            'distribution' => $distribution,
            'trend' => $trend,
            'journeys' => $journeys,
            'menteeOptions' => $this->scope->menteeOptions(),
            'filters' => $request->only(['mentee_id']),
        ]);
    }
}
