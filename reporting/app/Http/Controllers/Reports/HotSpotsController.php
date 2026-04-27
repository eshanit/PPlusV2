<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\District;
use App\Models\Facility;
use App\Models\Tool;
use App\Services\ReportScopeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class HotSpotsController extends Controller
{
    public function __construct(private readonly ReportScopeService $scope) {}

    public function __invoke(Request $request): Response
    {
        $toolId = $request->integer('tool_id') ?: null;
        $facilityId = $request->integer('facility_id') ?: null;
        $districtId = $request->integer('district_id') ?: null;

        $rows = DB::table('evaluation_items as ei')
            ->join('tool_categories as tc', 'tc.id', '=', 'ei.category_id')
            ->join('tools as t', 't.id', '=', 'ei.tool_id')
            ->leftJoin('session_item_scores as sis', 'sis.item_id', '=', 'ei.id')
            ->leftJoin('evaluation_sessions as es', 'es.id', '=', 'sis.session_id')
            ->where('t.slug', '!=', 'counselling')
            ->whereRaw(...$this->scope->scope('es'))
            ->when($toolId, fn ($q) => $q->where('ei.tool_id', $toolId))
            ->when($facilityId, fn ($q) => $q->where('es.facility_id', $facilityId))
            ->when($districtId, fn ($q) => $q->where('es.district_id', $districtId))
            ->select([
                'ei.id',
                'ei.slug',
                'ei.number',
                'ei.title',
                'ei.is_advanced',
                'ei.is_critical',
                'tc.name as category',
                't.id as tool_id',
                't.label as tool_label',
                't.sort_order as tool_sort',
                'tc.sort_order as cat_sort',
                'ei.sort_order as item_sort',
            ])
            ->selectRaw('COUNT(sis.mentee_score) as times_scored')
            ->selectRaw('ROUND(AVG(sis.mentee_score), 2) as avg_score')
            ->selectRaw('SUM(CASE WHEN sis.mentee_score >= 4 THEN 1 ELSE 0 END) as count_competent')
            ->groupBy(
                'ei.id', 'ei.slug', 'ei.number', 'ei.title', 'ei.is_advanced', 'ei.is_critical',
                'tc.name', 't.id', 't.label', 't.sort_order', 'tc.sort_order', 'ei.sort_order'
            )
            ->having('times_scored', '>', 0)
            ->orderBy('avg_score')
            ->get();

        $items = $rows->map(fn (object $row): array => [
            'id' => $row->id,
            'number' => $row->number,
            'title' => $row->title,
            'isAdvanced' => (bool) $row->is_advanced,
            'isCritical' => (bool) $row->is_critical,
            'category' => $row->category,
            'tool' => $row->tool_label,
            'toolId' => $row->tool_id,
            'timesScored' => (int) $row->times_scored,
            'avgScore' => $row->avg_score !== null ? (float) $row->avg_score : null,
            'pctCompetent' => (int) $row->times_scored > 0
                ? round(((int) $row->count_competent / (int) $row->times_scored) * 100, 1)
                : null,
        ])->all();

        $scoredItems = collect($items);
        $summary = [
            'totalScored' => $scoredItems->count(),
            'below3' => $scoredItems->filter(fn ($i) => $i['avgScore'] !== null && $i['avgScore'] < 3)->count(),
            'below4' => $scoredItems->filter(fn ($i) => $i['avgScore'] !== null && $i['avgScore'] < 4)->count(),
            'atCompetency' => $scoredItems->filter(fn ($i) => $i['avgScore'] !== null && $i['avgScore'] >= 4)->count(),
            'avgScore' => $scoredItems->count() > 0
                ? round($scoredItems->avg('avgScore'), 2)
                : null,
        ];

        // Tool-level breakdown
        $toolBreakdown = $scoredItems
            ->groupBy('tool')
            ->map(function ($toolItems) {
                $count = $toolItems->count();

                return [
                    'tool' => $toolItems->first()['tool'],
                    'toolId' => $toolItems->first()['toolId'],
                    'totalItems' => $count,
                    'avgScore' => $count > 0 ? round($toolItems->avg('avgScore'), 2) : null,
                    'itemsBelow3' => $toolItems->filter(fn ($i) => $i['avgScore'] !== null && $i['avgScore'] < 3)->count(),
                    'pctAtCompetency' => $count > 0
                        ? round($toolItems->filter(fn ($i) => $i['avgScore'] !== null && $i['avgScore'] >= 4)->count() / $count * 100, 1)
                        : null,
                ];
            })
            ->sortBy('avgScore')
            ->values()
            ->all();

        return Inertia::render('Reports/HotSpots', [
            'items' => $items,
            'toolBreakdown' => $toolBreakdown,
            'summary' => $summary,
            'tools' => Tool::where('slug', '!=', 'counselling')->orderBy('sort_order')->get(['id', 'label']),
            ...$this->scopedDropdowns(),
            'filters' => $request->only(['tool_id', 'district_id', 'facility_id']),
        ]);
    }

    /** @return array{districts: array<mixed>, facilities: array<mixed>} */
    private function scopedDropdowns(): array
    {
        $user = auth()->user();

        if ($user && ! $user->isAdmin() && $user->district_id) {
            $districts = District::where('id', $user->district_id)->get(['id', 'name']);
            $facilities = Facility::where('district_id', $user->district_id)->orderBy('name')->get(['id', 'name']);
        } else {
            $districts = District::orderBy('name')->get(['id', 'name']);
            $facilities = Facility::orderBy('name')->get(['id', 'name']);
        }

        return compact('districts', 'facilities');
    }
}
