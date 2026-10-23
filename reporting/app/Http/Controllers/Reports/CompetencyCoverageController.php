<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\District;
use App\Models\Facility;
use App\Models\Tool;
use App\Services\ReportScopeService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Competency coverage: competencies a journey has missed.
 *
 * Mentors evaluate only what they observe, so items can go unevaluated. A
 * journey cannot reach competency until every required item's latest score is
 * 4 or 5, so a never-evaluated item silently blocks it. Coverage is counted per
 * journey regardless of who evaluated, so an evaluator taking over a mentee can
 * see what the previous evaluator didn't cover.
 *
 * - Never evaluated: no score yet, or rated only N/A.
 * - Not evaluated recently: latest score below 4, last rated at least
 *   `stale` sessions ago. Achieved items are never flagged: under the
 *   competency rule they don't need re-rating.
 *
 * v_item_coverage is evaluated once per request; both views, the summary, the
 * item lists and the CSV are built from that one result.
 */
class CompetencyCoverageController extends Controller
{
    private const PER_PAGE = 25;

    private const DEFAULT_STALE = 2;

    public function __construct(private readonly ReportScopeService $scope) {}

    public function __invoke(Request $request): Response|StreamedResponse
    {
        $filters = $this->filters($request);
        $coverage = $this->fetchCoverage($filters);
        $journeys = $this->journeyRows($coverage, $filters);

        if ($request->input('export') === 'csv') {
            return $this->exportCsv($coverage, $journeys, $filters);
        }

        $view = $filters['view'];
        $rows = $view === 'competencies' ? $this->competencyRows($coverage, $journeys, $filters) : $journeys;

        $page = LengthAwarePaginator::resolveCurrentPage();
        $paginator = new LengthAwarePaginator(
            $rows->forPage($page, self::PER_PAGE)->values(),
            $rows->count(),
            self::PER_PAGE,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        $pageItems = collect($paginator->items());
        if ($view === 'journeys') {
            $missed = $this->missedItems($coverage, $filters)->groupBy('groupId');
            $pageItems = $pageItems->map(fn (array $j): array => [
                ...$j,
                'missed' => ($missed[$j['groupId']] ?? collect())->values()->all(),
            ]);
        }

        [$districts, $facilities] = $this->scopedDropdowns();

        return Inertia::render('Reports/CompetencyCoverage', [
            'view' => $view,
            'rows' => $pageItems->all(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'links' => $paginator->linkCollection()->toArray(),
            ],
            'summary' => [
                'journeys' => $journeys->count(),
                'journeysWithNever' => $journeys->where('neverCount', '>', 0)->count(),
                'neverItems' => (int) $journeys->sum('neverCount'),
                'staleItems' => (int) $journeys->sum('staleCount'),
            ],
            'tools' => Tool::where('slug', '!=', 'counselling')->orderBy('sort_order')->get(['id', 'label']),
            'districts' => $districts,
            'facilities' => $facilities,
            'menteeOptions' => $this->menteeOptions(),
            'evaluatorOptions' => $this->evaluatorOptions(),
            'filters' => array_filter([
                'tool_id' => $request->input('tool_id'),
                'district_id' => $filters['district_id'],
                'facility_id' => $filters['facility_id'],
                'mentee_id' => $filters['mentee_id'],
                'evaluator_id' => $request->input('evaluator_id'),
                'status' => $filters['status'] === 'in_progress' ? null : $filters['status'],
                'stale' => $filters['stale'] === self::DEFAULT_STALE ? null : (string) $filters['stale'],
                'advanced' => $filters['advanced'] ? '1' : null,
                'view' => $view === 'journeys' ? null : $view,
            ], fn ($v) => $v !== null && $v !== ''),
            'stale' => $filters['stale'],
            'currentUserId' => $request->user()?->id,
        ]);
    }

    /** @return array<string, mixed> */
    private function filters(Request $request): array
    {
        $evaluatorId = $request->input('evaluator_id');
        if ($evaluatorId === 'me') {
            $evaluatorId = $request->user()?->id;
        }

        return [
            'tool_id' => $request->input('tool_id'),
            'district_id' => $request->input('district_id'),
            'facility_id' => $request->input('facility_id'),
            'mentee_id' => $request->input('mentee_id'),
            'evaluator_id' => $evaluatorId ? (string) $evaluatorId : null,
            // in_progress (default) | competent | all
            'status' => in_array($request->input('status'), ['competent', 'all'], true) ? $request->input('status') : 'in_progress',
            'stale' => max(1, min(10, (int) ($request->input('stale') ?: self::DEFAULT_STALE))),
            'advanced' => $request->boolean('advanced'),
            'view' => $request->input('view') === 'competencies' ? 'competencies' : 'journeys',
        ];
    }

    /** All coverage rows (journey x tool item) matching the filters, in one query. */
    private function fetchCoverage(array $f): Collection
    {
        return DB::table('v_item_coverage as c')
            ->whereRaw(...$this->scope->scope('c'))
            ->when($f['tool_id'], fn ($q) => $q->where('c.tool_id', $f['tool_id']))
            ->when($f['district_id'], fn ($q) => $q->where('c.district_id', $f['district_id']))
            ->when($f['facility_id'], fn ($q) => $q->where('c.facility_id', $f['facility_id']))
            ->when($f['mentee_id'], fn ($q) => $q->where('c.mentee_id', $f['mentee_id']))
            // Journeys this evaluator has recorded at least one session in.
            ->when($f['evaluator_id'], fn ($q) => $q->whereIn('c.evaluation_group_id', function ($sub) use ($f) {
                $sub->select('evaluation_group_id')->from('evaluation_sessions')->where('evaluator_id', $f['evaluator_id']);
            }))
            ->orderBy('c.sort_order')
            ->get([
                'c.evaluation_group_id', 'c.mentee_id', 'c.tool_id', 'c.district_id', 'c.facility_id',
                'c.last_evaluator_id', 'c.last_session_date', 'c.total_sessions',
                'c.item_id', 'c.item_number', 'c.item_title', 'c.is_advanced', 'c.sort_order',
                'c.coverage_status', 'c.latest_score', 'c.last_evaluated_date', 'c.sessions_since_evaluated',
            ]);
    }

    private function isNever(object $r): bool
    {
        return in_array($r->coverage_status, ['never', 'only_na'], true);
    }

    private function isStale(object $r, int $stale): bool
    {
        return $r->coverage_status === 'pending' && (int) $r->sessions_since_evaluated >= $stale;
    }

    /** One row per journey, most missed first, filtered by journey status. */
    private function journeyRows(Collection $coverage, array $f): Collection
    {
        $groups = $coverage->groupBy('evaluation_group_id');
        $names = $this->nameMaps($coverage);
        $counted = fn (object $r): bool => $f['advanced'] || ! $r->is_advanced;

        return $groups
            ->map(function (Collection $items, string $groupId) use ($f, $names, $counted): array {
                $first = $items->first();
                $required = $items->filter(fn ($r) => ! $r->is_advanced);

                return [
                    'groupId' => $groupId,
                    'menteeId' => $first->mentee_id,
                    'mentee' => $names['users'][$first->mentee_id] ?? 'Unknown mentee',
                    'tool' => $names['tools'][$first->tool_id] ?? '',
                    'facility' => $names['facilities'][$first->facility_id] ?? null,
                    'district' => $names['districts'][$first->district_id] ?? null,
                    'lastEvaluator' => $names['users'][$first->last_evaluator_id] ?? null,
                    'lastSessionDate' => $first->last_session_date,
                    'totalSessions' => (int) $first->total_sessions,
                    'neverCount' => $items->filter(fn ($r) => $counted($r) && $this->isNever($r))->count(),
                    'staleCount' => $items->filter(fn ($r) => $counted($r) && $this->isStale($r, $f['stale']))->count(),
                    'requiredAchieved' => $required->where('coverage_status', 'achieved')->count(),
                    'requiredItems' => $required->count(),
                ];
            })
            ->filter(fn (array $j): bool => match ($f['status']) {
                'in_progress' => $j['requiredAchieved'] < $j['requiredItems'],
                'competent' => $j['requiredAchieved'] === $j['requiredItems'],
                default => true,
            })
            ->sortBy([['neverCount', 'desc'], ['staleCount', 'desc'], ['lastSessionDate', 'asc']])
            ->values();
    }

    /** One row per competency, across the journeys that passed the status filter. */
    private function competencyRows(Collection $coverage, Collection $journeys, array $f): Collection
    {
        $included = $journeys->pluck('groupId')->flip();
        $tools = $this->nameMaps($coverage)['tools'];

        return $coverage
            ->filter(fn ($r) => $included->has($r->evaluation_group_id) && ($f['advanced'] || ! $r->is_advanced))
            ->groupBy('item_id')
            ->map(function (Collection $rows) use ($f, $tools): array {
                $first = $rows->first();
                $journeyCount = $rows->count();
                $never = $rows->filter(fn ($r) => $this->isNever($r))->count();

                return [
                    'itemId' => (int) $first->item_id,
                    'number' => $first->item_number,
                    'title' => $first->item_title,
                    'isAdvanced' => (bool) $first->is_advanced,
                    'tool' => $tools[$first->tool_id] ?? '',
                    'journeys' => $journeyCount,
                    'neverCount' => $never,
                    'staleCount' => $rows->filter(fn ($r) => $this->isStale($r, $f['stale']))->count(),
                    'achievedCount' => $rows->where('coverage_status', 'achieved')->count(),
                    'neverPct' => $journeyCount > 0 ? (int) round($never / $journeyCount * 100) : 0,
                ];
            })
            ->sortBy([['neverCount', 'desc'], ['staleCount', 'desc'], ['tool', 'asc']])
            ->values();
    }

    /** Missed items: never evaluated, or below 4 and not evaluated recently. */
    private function missedItems(Collection $coverage, array $f): Collection
    {
        return $coverage
            ->filter(fn ($r) => ($f['advanced'] || ! $r->is_advanced)
                && ($this->isNever($r) || $this->isStale($r, $f['stale'])))
            ->map(fn (object $r): array => [
                'groupId' => $r->evaluation_group_id,
                'number' => $r->item_number,
                'title' => $r->item_title,
                'isAdvanced' => (bool) $r->is_advanced,
                'kind' => $r->coverage_status === 'pending' ? 'stale' : $r->coverage_status,
                'latestScore' => $r->latest_score !== null ? (int) $r->latest_score : null,
                'lastEvaluatedDate' => $r->last_evaluated_date,
                'sessionsSince' => (int) $r->sessions_since_evaluated,
            ])
            ->values();
    }

    /** Name lookups for the ids in the coverage rows. */
    private function nameMaps(Collection $coverage): array
    {
        static $cache = [];
        $key = spl_object_id($coverage);
        if (isset($cache[$key])) {
            return $cache[$key];
        }

        $userIds = $coverage->pluck('mentee_id')->merge($coverage->pluck('last_evaluator_id'))->filter()->unique()->values();

        return $cache[$key] = [
            'users' => DB::table('users')->whereIn('id', $userIds->all() ?: [''])->get(['id', 'firstname', 'lastname'])
                ->mapWithKeys(fn ($u) => [$u->id => trim("{$u->firstname} {$u->lastname}")])->all(),
            'tools' => DB::table('tools')->pluck('label', 'id')->all(),
            'facilities' => DB::table('facilities')->pluck('name', 'id')->all(),
            'districts' => DB::table('districts')->pluck('name', 'id')->all(),
        ];
    }

    private function exportCsv(Collection $coverage, Collection $journeys, array $f): StreamedResponse
    {
        $byGroup = $journeys->keyBy('groupId');
        $items = $this->missedItems($coverage, $f)->filter(fn (array $i) => $byGroup->has($i['groupId']));

        return response()->streamDownload(function () use ($byGroup, $items): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'mentee', 'tool', 'district', 'facility', 'last_evaluator', 'last_session_date', 'total_sessions',
                'required_achieved', 'required_items', 'item', 'item_title', 'advanced', 'coverage',
                'latest_score', 'last_evaluated_date', 'sessions_since_evaluated',
            ]);
            foreach ($items as $item) {
                $j = $byGroup[$item['groupId']];
                fputcsv($out, [
                    $j['mentee'], $j['tool'], $j['district'], $j['facility'], $j['lastEvaluator'], $j['lastSessionDate'],
                    $j['totalSessions'], $j['requiredAchieved'], $j['requiredItems'], $item['number'], $item['title'],
                    $item['isAdvanced'] ? 'yes' : 'no',
                    match ($item['kind']) { 'never' => 'never evaluated', 'only_na' => 'only N/A', default => 'not evaluated recently' },
                    $item['latestScore'], $item['lastEvaluatedDate'], $item['sessionsSince'],
                ]);
            }
            fclose($out);
        }, 'competency-coverage-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
    }

    /**
     * Mentees with journeys visible to the user. Read from evaluation_sessions
     * directly: the shared helper uses v_journey_summary, which is slow.
     *
     * @return array<int, array{id: string, name: string}>
     */
    private function menteeOptions(): array
    {
        return DB::table('evaluation_sessions as es')
            ->join('users as u', 'u.id', '=', 'es.mentee_id')
            ->whereRaw(...$this->scope->scope('es'))
            ->select('u.id', 'u.firstname', 'u.lastname')
            ->distinct()
            ->orderBy('u.firstname')
            ->get()
            ->map(fn (object $u): array => ['id' => $u->id, 'name' => trim("{$u->firstname} {$u->lastname}")])
            ->all();
    }

    /** @return array<int, array{id: string, name: string}> */
    private function evaluatorOptions(): array
    {
        return DB::table('evaluation_sessions as es')
            ->join('users as u', 'u.id', '=', 'es.evaluator_id')
            ->select('u.id', 'u.firstname', 'u.lastname')
            ->distinct()
            ->orderBy('u.firstname')
            ->get()
            ->map(fn (object $u): array => ['id' => $u->id, 'name' => trim("{$u->firstname} {$u->lastname}")])
            ->all();
    }

    /** @return array{0: array<int, array<string, mixed>>, 1: array<int, array<string, mixed>>} */
    private function scopedDropdowns(): array
    {
        $user = auth()->user();
        $dq = District::orderBy('name');
        $fq = Facility::orderBy('name');

        if ($user && ! $user->isAdmin() && ! $user->isEvaluator() && $user->district_id) {
            $dq->where('id', $user->district_id);
            $fq->where('district_id', $user->district_id);
        }

        return [
            $dq->get(['id', 'name'])->map(fn ($d) => ['id' => $d->id, 'name' => $d->name])->all(),
            $fq->get(['id', 'name'])->map(fn ($f) => ['id' => $f->id, 'name' => $f->name])->all(),
        ];
    }
}
