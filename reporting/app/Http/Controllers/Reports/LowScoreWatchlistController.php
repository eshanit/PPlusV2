<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\District;
use App\Models\EvaluationItem;
use App\Models\Tool;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class LowScoreWatchlistController extends Controller
{
    private const SORT_MAP = [
        'avg_score' => 'AVG(vlis.mentee_score)',
        'pct_at_goal' => 'SUM(CASE WHEN vlis.mentee_score >= 4 THEN 1 ELSE 0 END) / COUNT(*)',
        'journeys_below_4' => 'SUM(CASE WHEN vlis.mentee_score < 4 THEN 1 ELSE 0 END)',
        'total_journeys' => 'COUNT(*)',
    ];

    private function baseWhere(): string
    {
        $user = auth()->user();

        if (! $user || $user->isAdmin() || ! $user->district_id) {
            return '1=1';
        }

        return "vlis.district_id = {$user->district_id}";
    }

    private function scopedDistricts()
    {
        $user = auth()->user();
        if ($user && ! $user->isAdmin() && $user->district_id) {
            return District::where('id', $user->district_id)->get(['id', 'name']);
        }

        return District::orderBy('name')->get(['id', 'name']);
    }

    public function __invoke(Request $request): Response
    {
        $sort = array_key_exists($request->sort, self::SORT_MAP) ? $request->sort : 'avg_score';
        $direction = $request->direction === 'desc' ? 'desc' : 'asc';

        $paginator = EvaluationItem::query()
            ->select([
                'evaluation_items.id',
                'evaluation_items.number',
                'evaluation_items.title',
                'evaluation_items.tool_id',
                'tools.label as tool_label',
                DB::raw('ROUND(AVG(vlis.mentee_score), 2) as avg_score'),
                DB::raw('ROUND(SUM(CASE WHEN vlis.mentee_score >= 4 THEN 1 ELSE 0 END) / COUNT(*) * 100, 1) as pct_at_goal'),
                DB::raw('SUM(CASE WHEN vlis.mentee_score < 4 THEN 1 ELSE 0 END) as journeys_below_4'),
                DB::raw('COUNT(*) as total_journeys'),
            ])
            ->join('v_latest_item_scores as vlis', 'vlis.item_id', '=', 'evaluation_items.id')
            ->join('tools', 'tools.id', '=', 'evaluation_items.tool_id')
            ->where('tools.slug', '!=', 'counselling')
            ->whereRaw($this->baseWhere())
            ->when($request->tool_id, fn ($q) => $q->where('evaluation_items.tool_id', $request->tool_id))
            ->when($request->district_id, fn ($q) => $q->where('vlis.district_id', $request->district_id))
            ->groupBy(
                'evaluation_items.id',
                'evaluation_items.number',
                'evaluation_items.title',
                'evaluation_items.tool_id',
                'tools.label',
                'tools.sort_order',
            )
            ->orderByRaw(self::SORT_MAP[$sort].' '.$direction)
            ->paginate(50)
            ->withQueryString();

        $items = $paginator->map(fn (EvaluationItem $item): array => [
            'id' => $item->id,
            'number' => $item->number,
            'title' => $item->title,
            'tool' => $item->tool_label,
            'avgScore' => (float) $item->avg_score,
            'pctAtGoal' => (float) $item->pct_at_goal,
            'journeysBelow4' => (int) $item->journeys_below_4,
            'totalJourneys' => (int) $item->total_journeys,
        ]);

        return Inertia::render('Reports/LowScoreWatchlist', [
            'items' => $items,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'links' => $paginator->linkCollection()->toArray(),
            ],
            'tools' => Tool::where('slug', '!=', 'counselling')
                ->orderBy('sort_order')
                ->get(['id', 'label']),
            'districts' => $this->scopedDistricts(),
            'filters' => $request->only(['tool_id', 'district_id']),
            'sort' => $sort,
            'direction' => $direction,
        ]);
    }
}
