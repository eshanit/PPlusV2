<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\GapEntry;
use App\Models\Tool;
use App\Services\ReportScopeService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GapListController extends Controller
{
    private const SUPERVISION_LABELS = [
        'intensive_mentorship' => 'Intensive Mentorship',
        'ongoing_mentorship' => 'Ongoing Mentorship',
        'independent_practice' => 'Independent Practice',
    ];

    public function __construct(private readonly ReportScopeService $scope) {}

    public function __invoke(Request $request): Response
    {
        $toolId = $request->integer('tool_id') ?: null;
        $tool = $toolId ? Tool::find($toolId, ['id', 'label']) : null;

        $paginator = GapEntry::query()
            ->with(['mentee:id,firstname,lastname', 'tool:id,label'])
            ->whereRaw(...$this->scope->gapScope())
            ->when($toolId, fn ($q) => $q->where('tool_id', $toolId))
            ->when($request->status === 'open', fn ($q) => $q->whereNull('resolved_at'))
            ->when($request->status === 'resolved', fn ($q) => $q->whereNotNull('resolved_at'))
            ->orderByDesc('identified_at')
            ->paginate(25)
            ->withQueryString();

        $gaps = $paginator->map(fn (GapEntry $gap): array => [
            'id' => $gap->id,
            'mentee' => trim("{$gap->mentee?->firstname} {$gap->mentee?->lastname}"),
            'tool' => $gap->tool?->label,
            'identifiedAt' => $gap->identified_at?->toDateString(),
            'description' => $gap->description,
            'domains' => $gap->domains ?? [],
            'supervisionLevel' => $gap->supervision_level,
            'supervisionLabel' => self::SUPERVISION_LABELS[$gap->supervision_level] ?? null,
            'resolvedAt' => $gap->resolved_at?->toDateString(),
            'isResolved' => $gap->resolved_at !== null,
        ]);

        return Inertia::render('Reports/GapList', [
            'gaps' => $gaps,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'links' => $paginator->linkCollection()->toArray(),
            ],
            'tool' => $tool ? ['id' => $tool->id, 'label' => $tool->label] : null,
            'tools' => Tool::where('slug', '!=', 'counselling')
                ->orderBy('sort_order')
                ->get(['id', 'label']),
            'filters' => $request->only(['tool_id', 'status']),
        ]);
    }
}
