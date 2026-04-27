<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class JourneyHeatmapController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $groupId = $request->input('group_id');

        if (! $groupId) {
            return Inertia::render('Reports/JourneyHeatmap', [
                'journey' => null,
                'sessions' => [],
                'rows' => [],
                'categories' => [],
            ]);
        }

        $journey = DB::table('v_journey_summary')
            ->where('evaluation_group_id', $groupId)
            ->first();

        if (! $journey) {
            abort(404);
        }

        // Sessions ordered by session number
        $sessions = DB::table('v_sessions_numbered as vsn')
            ->where('vsn.evaluation_group_id', $groupId)
            ->orderBy('vsn.session_number')
            ->get(['vsn.id', 'vsn.session_number', 'vsn.eval_date']);

        $sessionIds = $sessions->pluck('id');

        // All scores for this journey (including null = N/A)
        $scores = DB::table('session_item_scores as sis')
            ->whereIn('sis.session_id', $sessionIds)
            ->get(['sis.session_id', 'sis.item_id', 'sis.mentee_score']);

        // score map: item_id → session_id → score|null
        $scoreMap = [];
        foreach ($scores as $s) {
            $scoreMap[$s->item_id][$s->session_id] = $s->mentee_score;
        }

        // All items for this tool, ordered by category then item
        $items = DB::table('evaluation_items as ei')
            ->join('tool_categories as tc', 'tc.id', '=', 'ei.category_id')
            ->where('ei.tool_id', $journey->tool_id)
            ->orderBy('tc.sort_order')
            ->orderBy('ei.sort_order')
            ->select([
                'ei.id',
                'ei.number',
                'ei.title',
                'ei.is_advanced',
                'ei.is_critical',
                'tc.name as category',
                'tc.sort_order as cat_sort',
            ])
            ->get();

        $rows = $items->map(function (object $item) use ($sessions, $scoreMap): array {
            $itemScores = $scoreMap[$item->id] ?? [];

            $cells = $sessions->map(function (object $s) use ($itemScores): array {
                if (! array_key_exists($s->id, $itemScores)) {
                    return ['present' => false, 'score' => null];
                }

                return ['present' => true, 'score' => $itemScores[$s->id]];
            })->all();

            $scored = array_filter($cells, fn ($c) => $c['present'] && $c['score'] !== null);
            $avgScore = count($scored) > 0
                ? round(array_sum(array_column($scored, 'score')) / count($scored), 2)
                : null;

            return [
                'id' => $item->id,
                'number' => $item->number,
                'title' => $item->title,
                'isAdvanced' => (bool) $item->is_advanced,
                'isCritical' => (bool) $item->is_critical,
                'category' => $item->category,
                'cells' => $cells,
                'avgScore' => $avgScore,
            ];
        })->all();

        return Inertia::render('Reports/JourneyHeatmap', [
            'journey' => [
                'groupId' => $journey->evaluation_group_id,
                'mentee' => trim("{$journey->mentee_firstname} {$journey->mentee_lastname}"),
                'tool' => $journey->tool_label,
                'toolId' => $journey->tool_id,
                'facility' => $journey->facility_name,
                'district' => $journey->district_name,
                'status' => $journey->competency_status,
                'totalSessions' => (int) $journey->total_sessions,
            ],
            'sessions' => $sessions->map(fn (object $s): array => [
                'id' => $s->id,
                'number' => (int) $s->session_number,
                'date' => $s->eval_date,
            ])->all(),
            'rows' => $rows,
        ]);
    }
}
