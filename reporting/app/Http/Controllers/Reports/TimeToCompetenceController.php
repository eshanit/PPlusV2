<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class TimeToCompetenceController extends Controller
{
    private static array $BIN_LABELS = ['0–30d', '31–60d', '61–90d', '91–180d', '181d+'];

    public function __invoke(Request $request): Response
    {
        $toolId = $request->input('tool_id');

        $rows = DB::table('v_evaluation_group_status as vgs')
            ->join('tools', 'tools.id', '=', 'vgs.tool_id')
            ->where('tools.slug', '!=', 'counselling')
            ->whereNotNull('vgs.days_to_basic_competence')
            ->when($toolId, fn ($q) => $q->where('vgs.tool_id', $toolId))
            ->get(['tools.label as tool_label', 'vgs.days_to_basic_competence']);

        $toolNames = $rows->pluck('tool_label')->unique()->sort()->values();

        $series = $toolNames->map(function (string $toolName) use ($rows): array {
            $toolRows = $rows->where('tool_label', $toolName);

            return [
                'name' => $toolName,
                'data' => [
                    $toolRows->filter(fn ($r) => (int) $r->days_to_basic_competence <= 30)->count(),
                    $toolRows->filter(fn ($r) => (int) $r->days_to_basic_competence >= 31 && (int) $r->days_to_basic_competence <= 60)->count(),
                    $toolRows->filter(fn ($r) => (int) $r->days_to_basic_competence >= 61 && (int) $r->days_to_basic_competence <= 90)->count(),
                    $toolRows->filter(fn ($r) => (int) $r->days_to_basic_competence >= 91 && (int) $r->days_to_basic_competence <= 180)->count(),
                    $toolRows->filter(fn ($r) => (int) $r->days_to_basic_competence > 180)->count(),
                ],
            ];
        })->values()->all();

        $summary = DB::table('v_evaluation_group_status as vgs')
            ->join('tools', 'tools.id', '=', 'vgs.tool_id')
            ->where('tools.slug', '!=', 'counselling')
            ->whereNotNull('vgs.days_to_basic_competence')
            ->when($toolId, fn ($q) => $q->where('vgs.tool_id', $toolId))
            ->selectRaw('tools.label as tool_label, tools.sort_order')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('ROUND(AVG(vgs.days_to_basic_competence), 1) as avg_days')
            ->selectRaw('MIN(vgs.days_to_basic_competence) as min_days')
            ->selectRaw('MAX(vgs.days_to_basic_competence) as max_days')
            ->selectRaw('ROUND(AVG(vgs.sessions_to_basic_competence), 1) as avg_sessions')
            ->groupBy('tools.id', 'tools.label', 'tools.sort_order')
            ->orderBy('tools.sort_order')
            ->get()
            ->map(fn (object $r): array => [
                'tool' => $r->tool_label,
                'total' => (int) $r->total,
                'avgDays' => (float) $r->avg_days,
                'minDays' => (int) $r->min_days,
                'maxDays' => (int) $r->max_days,
                'avgSessions' => (float) $r->avg_sessions,
            ])
            ->all();

        $tools = DB::table('tools')
            ->where('slug', '!=', 'counselling')
            ->orderBy('sort_order')
            ->get(['id', 'label'])
            ->map(fn ($t) => ['id' => (int) $t->id, 'label' => $t->label])
            ->all();

        return Inertia::render('Reports/TimeToCompetence', [
            'binLabels' => self::$BIN_LABELS,
            'series' => $series,
            'summary' => $summary,
            'tools' => $tools,
            'filters' => ['tool_id' => $toolId],
        ]);
    }
}
