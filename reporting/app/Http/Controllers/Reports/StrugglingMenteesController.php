<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\District;
use App\Models\Facility;
use App\Models\JourneySummary;
use App\Models\Tool;
use App\Services\ReportScopeService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class StrugglingMenteesController extends Controller
{
    private const PER_PAGE = 25;

    public function __construct(private readonly ReportScopeService $scope) {}

    public function __invoke(Request $request): Response
    {
        $toolId = $request->input('tool_id');
        $districtId = $request->input('district_id');
        $facilityId = $request->input('facility_id');

        // One row per journey (mentee+tool) — the mentee-side analog of
        // Hot Spots needs to roll these up per mentee, across every tool
        // they've been evaluated on, which v_journey_summary doesn't do on
        // its own.
        $journeys = JourneySummary::query()
            ->whereRaw(...$this->scope->scope('v_journey_summary'))
            ->when($toolId, fn ($q) => $q->where('v_journey_summary.tool_id', $toolId))
            ->when($districtId, fn ($q) => $q->where('v_journey_summary.district_id', $districtId))
            ->when($facilityId, fn ($q) => $q->where('v_journey_summary.facility_id', $facilityId))
            ->whereNotNull('v_journey_summary.latest_avg_score')
            ->get();

        $mentees = $this->rollUpByMentee($journeys);

        $page = LengthAwarePaginator::resolveCurrentPage();
        $paginator = new LengthAwarePaginator(
            $mentees->forPage($page, self::PER_PAGE)->values(),
            $mentees->count(),
            self::PER_PAGE,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        $summary = [
            'totalMentees' => $mentees->count(),
            'avgScore' => $mentees->isNotEmpty() ? round($mentees->avg('avgScore'), 2) : null,
            'below3' => $mentees->filter(fn (array $m): bool => $m['avgScore'] < 3)->count(),
            'below4' => $mentees->filter(fn (array $m): bool => $m['avgScore'] < 4)->count(),
        ];

        [$districts, $facilities] = $this->scopedDropdowns();

        return Inertia::render('Reports/StrugglingMentees', [
            'mentees' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'links' => $paginator->linkCollection()->toArray(),
            ],
            'summary' => $summary,
            'tools' => Tool::where('slug', '!=', 'counselling')->orderBy('sort_order')->get(['id', 'label']),
            'districts' => $districts,
            'facilities' => $facilities,
            'filters' => $request->only(['tool_id', 'district_id', 'facility_id']),
        ]);
    }

    /**
     * @param  Collection<int, JourneySummary>  $journeys
     * @return Collection<int, array<string, mixed>>
     */
    private function rollUpByMentee(Collection $journeys): Collection
    {
        return $journeys
            ->groupBy('mentee_id')
            ->map(function (Collection $group): array {
                $first = $group->first();
                $weakest = $group->sortBy('latest_avg_score')->first();

                return [
                    'menteeId' => $first->mentee_id,
                    'mentee' => trim("{$first->mentee_firstname} {$first->mentee_lastname}"),
                    'district' => $first->district_name,
                    'facility' => $first->facility_name,
                    'avgScore' => round($group->avg('latest_avg_score'), 2),
                    'totalJourneys' => $group->count(),
                    'journeysBelowCompetency' => $group->filter(fn (JourneySummary $j): bool => $j->latest_avg_score < 4)->count(),
                    'openGaps' => (int) $group->sum('open_gaps'),
                    'weakestTool' => $weakest->tool_label,
                    'weakestToolScore' => (float) $weakest->latest_avg_score,
                    'weakestGroupId' => $weakest->evaluation_group_id,
                ];
            })
            ->sortBy('avgScore')
            ->values();
    }

    /** @return array{0: array<int, array<string, mixed>>, 1: array<int, array<string, mixed>>} */
    private function scopedDropdowns(): array
    {
        $user = auth()->user();
        $dq = District::orderBy('name');
        $fq = Facility::orderBy('name');

        if ($user && ! $user->isAdmin() && $user->district_id) {
            $dq->where('id', $user->district_id);
            $fq->where('district_id', $user->district_id);
        }

        return [
            $dq->get(['id', 'name'])->map(fn ($d) => ['id' => $d->id, 'name' => $d->name])->all(),
            $fq->get(['id', 'name'])->map(fn ($f) => ['id' => $f->id, 'name' => $f->name])->all(),
        ];
    }
}
