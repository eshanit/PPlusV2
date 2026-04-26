<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class NeedsAttentionController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $toolId = $request->input('tool_id');
        $districtId = $request->input('district_id');

        $base = DB::table('v_journey_summary')
            ->where('competency_status', 'in_progress')
            ->whereRaw('DATEDIFF(CURDATE(), latest_session_date) >= 30')
            ->when($toolId, fn ($q) => $q->where('tool_id', $toolId))
            ->when($districtId, fn ($q) => $q->where('district_id', $districtId));

        $paginator = (clone $base)
            ->selectRaw('
                evaluation_group_id, mentee_firstname, mentee_lastname,
                tool_label, district_name, facility_name,
                total_sessions, latest_avg_score, open_gaps,
                DATEDIFF(CURDATE(), latest_session_date) as days_stale
            ')
            ->orderByRaw('days_stale DESC')
            ->paginate(25)
            ->withQueryString();

        $items = $paginator->map(fn (object $j): array => [
            'groupId' => $j->evaluation_group_id,
            'mentee' => trim("{$j->mentee_firstname} {$j->mentee_lastname}"),
            'tool' => $j->tool_label,
            'district' => $j->district_name,
            'facility' => $j->facility_name,
            'totalSessions' => (int) $j->total_sessions,
            'latestAvgScore' => $j->latest_avg_score !== null ? round((float) $j->latest_avg_score, 1) : null,
            'openGaps' => (int) $j->open_gaps,
            'daysStale' => (int) $j->days_stale,
        ]);

        $itemsMeta = [
            'current_page' => $paginator->currentPage(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'links' => $paginator->linkCollection()->toArray(),
        ];

        $chartData = DB::table('v_journey_summary')
            ->where('competency_status', 'in_progress')
            ->whereRaw('DATEDIFF(CURDATE(), latest_session_date) >= 30')
            ->selectRaw('DATEDIFF(CURDATE(), latest_session_date) as days_stale, tool_label')
            ->get();

        $binLabels = ['30–60d', '61–90d', '91–180d', '181d+'];
        $toolNames = $chartData->pluck('tool_label')->unique()->sort()->values();

        $series = $toolNames->map(function (string $toolName) use ($chartData): array {
            $toolRows = $chartData->where('tool_label', $toolName);

            return [
                'name' => $toolName,
                'data' => [
                    $toolRows->filter(fn ($r) => (int) $r->days_stale <= 60)->count(),
                    $toolRows->filter(fn ($r) => (int) $r->days_stale >= 61 && (int) $r->days_stale <= 90)->count(),
                    $toolRows->filter(fn ($r) => (int) $r->days_stale >= 91 && (int) $r->days_stale <= 180)->count(),
                    $toolRows->filter(fn ($r) => (int) $r->days_stale > 180)->count(),
                ],
            ];
        })->values()->all();

        $tools = DB::table('tools')
            ->where('slug', '!=', 'counselling')
            ->orderBy('sort_order')
            ->get(['id', 'label'])
            ->map(fn ($t) => ['id' => (int) $t->id, 'label' => $t->label])
            ->all();

        $districts = DB::table('districts')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($d) => ['id' => (int) $d->id, 'name' => $d->name])
            ->all();

        return Inertia::render('Reports/NeedsAttention', [
            'items' => $items,
            'itemsMeta' => $itemsMeta,
            'binLabels' => $binLabels,
            'series' => $series,
            'tools' => $tools,
            'districts' => $districts,
            'filters' => ['tool_id' => $toolId, 'district_id' => $districtId],
        ]);
    }
}
