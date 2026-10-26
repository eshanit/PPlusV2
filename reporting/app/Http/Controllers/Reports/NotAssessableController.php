<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\District;
use App\Models\Facility;
use App\Models\Tool;
use App\Services\ReportScopeService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Competencies marked not assessable at the mentee's facility (NOT_ASSESSABLE.md).
 *
 * A marked item doesn't count towards the mentee's competency until it is
 * scored. Grouped by facility and reason, the marks point to resource gaps:
 * several mentees at one facility with C11 "Medicine not available" suggests
 * a supply problem rather than a training one.
 *
 * Only marks still in force are counted: the item's latest entry in the
 * journey is the mark (v_item_coverage.coverage_status = 'not_assessable').
 */
class NotAssessableController extends Controller
{
    /** Labels for the reason codes. Keep in step with NOT_ASSESSABLE_REASONS in the monitoring app. */
    public const REASONS = [
        'medicine_unavailable' => 'Medicine not available',
        'lab_test_unavailable' => 'Laboratory test not available',
        'equipment_unavailable' => 'Equipment or imaging not available',
        'referral_unavailable' => 'Referral service not available',
        'service_not_offered' => 'Service not offered at this facility',
        'patient_group_not_seen' => 'Patient group not seen at this facility',
        'not_locally_relevant' => 'Not relevant in the local context',
        'outside_scope_of_practice' => "Outside the mentee's scope of practice",
        'other' => 'Other',
    ];

    private const DETAIL_LIMIT = 200;

    public function __construct(private readonly ReportScopeService $scope) {}

    public function __invoke(Request $request): Response|StreamedResponse
    {
        $filters = [
            'tool_id' => $request->input('tool_id'),
            'district_id' => $request->input('district_id'),
            'facility_id' => $request->input('facility_id'),
            'reason' => array_key_exists((string) $request->input('reason'), self::REASONS) ? $request->input('reason') : null,
        ];

        $marks = $this->fetchMarks($filters);

        if ($request->input('export') === 'csv') {
            return $this->exportCsv($marks);
        }

        $byFacility = $marks
            ->groupBy(fn (array $m) => $m['facilityId'].'|'.$m['reason'])
            ->map(function (Collection $rows): array {
                $first = $rows->first();

                return [
                    'facility' => $first['facility'] ?? 'Unknown facility',
                    'district' => $first['district'],
                    'reason' => $first['reason'],
                    'reasonLabel' => $first['reasonLabel'],
                    'items' => $rows->count(),
                    'mentees' => $rows->pluck('menteeId')->unique()->count(),
                    // Item numbers with how many mentees each, most common first.
                    'itemSummary' => $rows->groupBy('itemKey')
                        ->map(fn (Collection $r) => ['number' => $r->first()['number'], 'tool' => $r->first()['tool'], 'count' => $r->count()])
                        ->sortByDesc('count')->values()->all(),
                ];
            })
            ->sortBy([['items', 'desc'], ['facility', 'asc']])
            ->values();

        $byItem = $marks
            ->groupBy('itemKey')
            ->map(function (Collection $rows): array {
                $first = $rows->first();

                return [
                    'number' => $first['number'],
                    'title' => $first['title'],
                    'tool' => $first['tool'],
                    'journeys' => $rows->count(),
                    'facilities' => $rows->pluck('facilityId')->unique()->count(),
                    'reasons' => $rows->countBy('reasonLabel')->sortDesc()->all(),
                ];
            })
            ->sortBy([['journeys', 'desc'], ['tool', 'asc']])
            ->values();

        [$districts, $facilities] = $this->scopedDropdowns();

        return Inertia::render('Reports/NotAssessable', [
            'summary' => [
                'items' => $marks->count(),
                'journeys' => $marks->pluck('groupId')->unique()->count(),
                'mentees' => $marks->pluck('menteeId')->unique()->count(),
                'facilities' => $marks->pluck('facilityId')->filter()->unique()->count(),
            ],
            'byFacility' => $byFacility->all(),
            'byItem' => $byItem->take(20)->all(),
            'details' => $marks->sortByDesc('markedAt')->take(self::DETAIL_LIMIT)->values()->all(),
            'detailLimit' => self::DETAIL_LIMIT,
            'reasons' => collect(self::REASONS)->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values()->all(),
            'tools' => Tool::where('slug', '!=', 'counselling')->orderBy('sort_order')->get(['id', 'label']),
            'districts' => $districts,
            'facilities' => $facilities,
            'filters' => array_filter($filters, fn ($v) => $v !== null && $v !== ''),
        ]);
    }

    /**
     * Marks in force, one per journey and item, with when and by whom the
     * latest mark was made.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function fetchMarks(array $f): Collection
    {
        $rows = DB::table('v_item_coverage as c')
            ->whereRaw(...$this->scope->scope('c'))
            ->where('c.coverage_status', 'not_assessable')
            ->when($f['tool_id'], fn ($q) => $q->where('c.tool_id', $f['tool_id']))
            ->when($f['district_id'], fn ($q) => $q->where('c.district_id', $f['district_id']))
            ->when($f['facility_id'], fn ($q) => $q->where('c.facility_id', $f['facility_id']))
            ->when($f['reason'], fn ($q) => $q->where('c.not_assessable_reason', $f['reason']))
            ->orderBy('c.sort_order')
            ->get([
                'c.evaluation_group_id', 'c.mentee_id', 'c.tool_id', 'c.district_id', 'c.facility_id',
                'c.item_id', 'c.item_number', 'c.item_title', 'c.is_advanced',
                'c.not_assessable_reason', 'c.not_assessable_note',
            ]);

        if ($rows->isEmpty()) {
            return collect();
        }

        // The latest mark for each journey and item: its date and evaluator.
        $markedBy = DB::table('session_item_scores as sis')
            ->join('evaluation_sessions as es', 'es.id', '=', 'sis.session_id')
            ->whereNotNull('sis.not_assessable_reason')
            ->whereIn('es.evaluation_group_id', $rows->pluck('evaluation_group_id')->unique()->all())
            ->orderBy('es.eval_date')
            ->orderByRaw('COALESCE(es.day_round, 999999)')
            ->orderBy('es.created_at')
            ->orderBy('sis.round_index')
            ->get(['es.evaluation_group_id', 'sis.item_id', 'es.eval_date', 'es.evaluator_id'])
            ->keyBy(fn ($m) => $m->evaluation_group_id.'|'.$m->item_id);

        $userIds = $rows->pluck('mentee_id')->merge($markedBy->pluck('evaluator_id'))->filter()->unique()->all();
        $users = DB::table('users')->whereIn('id', $userIds ?: [''])->get(['id', 'firstname', 'lastname'])
            ->mapWithKeys(fn ($u) => [$u->id => trim("{$u->firstname} {$u->lastname}")]);
        $tools = DB::table('tools')->pluck('label', 'id');
        $facilities = DB::table('facilities')->pluck('name', 'id');
        $districts = DB::table('districts')->pluck('name', 'id');

        return $rows->map(function (object $r) use ($markedBy, $users, $tools, $facilities, $districts): array {
            $mark = $markedBy[$r->evaluation_group_id.'|'.$r->item_id] ?? null;

            return [
                'groupId' => $r->evaluation_group_id,
                'menteeId' => $r->mentee_id,
                'mentee' => $users[$r->mentee_id] ?? 'Unknown mentee',
                'tool' => $tools[$r->tool_id] ?? '',
                'facilityId' => $r->facility_id,
                'facility' => $facilities[$r->facility_id] ?? null,
                'district' => $districts[$r->district_id] ?? null,
                'itemKey' => (string) $r->item_id,
                'number' => $r->item_number,
                'title' => $r->item_title,
                'isAdvanced' => (bool) $r->is_advanced,
                'reason' => $r->not_assessable_reason,
                'reasonLabel' => self::REASONS[$r->not_assessable_reason] ?? $r->not_assessable_reason,
                'note' => $r->not_assessable_note,
                'markedAt' => $mark?->eval_date,
                'markedBy' => $mark ? ($users[$mark->evaluator_id] ?? null) : null,
            ];
        });
    }

    private function exportCsv(Collection $marks): StreamedResponse
    {
        return response()->streamDownload(function () use ($marks): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['mentee', 'tool', 'district', 'facility', 'item', 'item_title', 'advanced', 'reason', 'note', 'marked_on', 'marked_by']);
            foreach ($marks as $m) {
                fputcsv($out, [
                    $m['mentee'], $m['tool'], $m['district'], $m['facility'], $m['number'], $m['title'],
                    $m['isAdvanced'] ? 'yes' : 'no', $m['reasonLabel'], $m['note'], $m['markedAt'], $m['markedBy'],
                ]);
            }
            fclose($out);
        }, 'not-assessable-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
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
