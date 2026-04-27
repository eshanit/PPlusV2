<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\EvaluationItem;
use App\Services\ReportScopeService;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ItemAnalysisController extends Controller
{
    public function __construct(private readonly ReportScopeService $scope) {}

    public function __invoke(int $id): Response
    {
        $item = EvaluationItem::with(['tool', 'category'])->findOrFail($id);

        // Summary stats
        $statsRow = DB::table('session_item_scores as sis')
            ->join('evaluation_sessions as es', 'es.id', '=', 'sis.session_id')
            ->where('sis.item_id', $id)
            ->whereRaw(...$this->scope->scope('es'))
            ->selectRaw('COUNT(sis.mentee_score) as times_scored')
            ->selectRaw('ROUND(AVG(sis.mentee_score), 2) as avg_score')
            ->selectRaw('SUM(CASE WHEN sis.mentee_score >= 4 THEN 1 ELSE 0 END) as count_competent')
            ->selectRaw('COUNT(DISTINCT es.evaluation_group_id) as journey_count')
            ->first();

        $timesScored = (int) ($statsRow->times_scored ?? 0);

        $stats = [
            'avgScore' => $statsRow->avg_score !== null ? (float) $statsRow->avg_score : null,
            'pctCompetent' => $timesScored > 0
                ? round(((int) $statsRow->count_competent / $timesScored) * 100, 1)
                : null,
            'timesScored' => $timesScored,
            'journeyCount' => (int) ($statsRow->journey_count ?? 0),
        ];

        // Score distribution (how many times each score 1-5 was given)
        $distRows = DB::table('session_item_scores as sis')
            ->join('evaluation_sessions as es', 'es.id', '=', 'sis.session_id')
            ->where('sis.item_id', $id)
            ->whereNotNull('sis.mentee_score')
            ->whereRaw(...$this->scope->scope('es'))
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
        $trend = DB::table('session_item_scores as sis')
            ->join('v_sessions_numbered as vsn', 'vsn.id', '=', 'sis.session_id')
            ->where('sis.item_id', $id)
            ->whereNotNull('sis.mentee_score')
            ->whereRaw(...$this->scope->scope('vsn'))
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
            ->select([
                'vlis.evaluation_group_id',
                'u.firstname',
                'u.lastname',
                'f.name as facility',
                'vlis.mentee_score as latest_score',
                'vlis.score_date',
            ])
            ->selectRaw(
                '(SELECT COUNT(sis3.id) FROM session_item_scores sis3
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
        ]);
    }
}
