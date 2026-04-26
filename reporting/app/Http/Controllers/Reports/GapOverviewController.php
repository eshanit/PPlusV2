<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Tool;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class GapOverviewController extends Controller
{
    private const DOMAIN_OPTIONS = [
        'knowledge' => 'Knowledge',
        'critical_reasoning' => 'Critical Reasoning',
        'clinical_skills' => 'Clinical Skills',
        'communication' => 'Communication',
        'attitude' => 'Attitude',
    ];

    public function __invoke(Request $request): Response
    {
        $base = DB::table('gap_entries')
            ->when($request->tool_id, fn ($q) => $q->where('tool_id', $request->tool_id))
            ->when($request->domain, fn ($q) => $q->whereJsonContains('domains', $request->domain))
            ->when($request->status === 'open', fn ($q) => $q->whereNull('resolved_at'))
            ->when($request->status === 'resolved', fn ($q) => $q->whereNotNull('resolved_at'));

        $summary = (clone $base)
            ->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN resolved_at IS NULL THEN 1 ELSE 0 END) as open_count,
                SUM(CASE WHEN resolved_at IS NOT NULL THEN 1 ELSE 0 END) as resolved_count,
                ROUND(AVG(CASE WHEN resolved_at IS NOT NULL THEN DATEDIFF(resolved_at, identified_at) END), 1) as avg_days_to_resolve
            ')
            ->first();

        $byTool = (clone $base)
            ->join('tools', 'tools.id', '=', 'gap_entries.tool_id')
            ->select([
                'tools.id as tool_id',
                'tools.label as tool_label',
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN gap_entries.resolved_at IS NULL THEN 1 ELSE 0 END) as open_count'),
                DB::raw('SUM(CASE WHEN gap_entries.resolved_at IS NOT NULL THEN 1 ELSE 0 END) as resolved_count'),
                DB::raw('ROUND(SUM(CASE WHEN gap_entries.resolved_at IS NOT NULL THEN 1 ELSE 0 END) / COUNT(*) * 100, 1) as pct_resolved'),
                DB::raw('ROUND(AVG(CASE WHEN gap_entries.resolved_at IS NOT NULL THEN DATEDIFF(gap_entries.resolved_at, gap_entries.identified_at) END), 1) as avg_days_to_resolve'),
            ])
            ->groupBy('tools.id', 'tools.label', 'tools.sort_order')
            ->orderByDesc('open_count')
            ->get()
            ->map(fn (object $row): array => [
                'toolId' => $row->tool_id,
                'tool' => $row->tool_label,
                'total' => (int) $row->total,
                'open' => (int) $row->open_count,
                'resolved' => (int) $row->resolved_count,
                'pctResolved' => (float) $row->pct_resolved,
                'avgDaysToResolve' => $row->avg_days_to_resolve !== null ? (float) $row->avg_days_to_resolve : null,
            ])
            ->all();

        $bySupervision = DB::table('gap_entries')
            ->select('supervision_level', DB::raw('COUNT(*) as total'))
            ->whereNull('resolved_at')
            ->when($request->tool_id, fn ($q) => $q->where('tool_id', $request->tool_id))
            ->when($request->domain, fn ($q) => $q->whereJsonContains('domains', $request->domain))
            ->whereNotNull('supervision_level')
            ->groupBy('supervision_level')
            ->orderByDesc('total')
            ->get()
            ->map(fn (object $row): array => [
                'level' => $row->supervision_level,
                'label' => match ($row->supervision_level) {
                    'intensive_mentorship' => 'Intensive Mentorship',
                    'ongoing_mentorship' => 'Ongoing Mentorship',
                    'independent_practice' => 'Independent Practice',
                    default => $row->supervision_level,
                },
                'total' => (int) $row->total,
            ])
            ->all();

        return Inertia::render('Reports/GapOverview', [
            'summary' => [
                'total' => (int) ($summary->total ?? 0),
                'open' => (int) ($summary->open_count ?? 0),
                'resolved' => (int) ($summary->resolved_count ?? 0),
                'avgDaysToResolve' => $summary->avg_days_to_resolve !== null ? (float) $summary->avg_days_to_resolve : null,
            ],
            'byTool' => $byTool,
            'bySupervision' => $bySupervision,
            'tools' => Tool::where('slug', '!=', 'counselling')
                ->orderBy('sort_order')
                ->get(['id', 'label']),
            'domainOptions' => self::DOMAIN_OPTIONS,
            'filters' => $request->only(['tool_id', 'domain', 'status']),
        ]);
    }
}
