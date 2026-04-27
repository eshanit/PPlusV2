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

class ToolAnalysisController extends Controller
{
    public function __construct(private readonly ReportScopeService $scope) {}

    public function __invoke(Request $request): Response
    {
        $toolId = $request->input('tool_id');
        $districtId = $request->input('district_id');
        $facilityId = $request->input('facility_id');

        $selectedTool = $toolId
            ? Tool::where('id', $toolId)->first(['id', 'slug', 'label'])
            : null;

        $items = [];
        $summary = null;

        if ($selectedTool) {
            $rows = DB::table('evaluation_items as ei')
                ->leftJoin('tool_categories as tc', 'tc.id', '=', 'ei.category_id')
                ->leftJoin('session_item_scores as sis', 'sis.item_id', '=', 'ei.id')
                ->leftJoin('evaluation_sessions as es', 'es.id', '=', 'sis.session_id')
                ->where('ei.tool_id', $toolId)
                ->whereRaw(...$this->scope->scope('es'))
                ->when($districtId, fn ($q) => $q->where('es.district_id', $districtId))
                ->when($facilityId, fn ($q) => $q->where('es.facility_id', $facilityId))
                ->select([
                    'ei.id',
                    'ei.slug',
                    'ei.number',
                    'ei.title',
                    'ei.is_advanced',
                    'ei.sort_order',
                    'tc.name as category',
                    'tc.sort_order as cat_sort',
                ])
                ->selectRaw('COUNT(sis.mentee_score) as times_scored')
                ->selectRaw('ROUND(AVG(sis.mentee_score), 2) as avg_score')
                ->selectRaw('SUM(CASE WHEN sis.mentee_score >= 4 THEN 1 ELSE 0 END) as count_competent')
                ->selectRaw('COUNT(sis.id) - COUNT(sis.mentee_score) as count_na')
                ->groupBy('ei.id', 'ei.slug', 'ei.number', 'ei.title', 'ei.is_advanced', 'ei.sort_order', 'tc.name', 'tc.sort_order')
                ->orderBy('tc.sort_order')
                ->orderBy('ei.sort_order')
                ->get();

            $items = $rows->map(fn (object $row): array => [
                'id' => $row->id,
                'slug' => $row->slug,
                'number' => $row->number,
                'title' => $row->title,
                'isAdvanced' => (bool) $row->is_advanced,
                'category' => $row->category ?? 'Uncategorised',
                'timesScored' => (int) $row->times_scored,
                'avgScore' => $row->avg_score !== null ? (float) $row->avg_score : null,
                'countCompetent' => (int) $row->count_competent,
                'countNa' => (int) $row->count_na,
                'pctCompetent' => (int) $row->times_scored > 0
                    ? round(((int) $row->count_competent / (int) $row->times_scored) * 100, 1)
                    : null,
            ])->all();

            $scored = collect($items)->filter(fn ($i) => $i['avgScore'] !== null);

            $summary = [
                'avgScore' => $scored->isNotEmpty() ? round($scored->avg('avgScore'), 2) : null,
                'pctAtCompetency' => $scored->isNotEmpty()
                    ? round(($scored->filter(fn ($i) => $i['avgScore'] >= 4.0)->count() / $scored->count()) * 100, 1)
                    : null,
                'itemsBelowThreshold' => $scored->filter(fn ($i) => $i['avgScore'] < 3.0)->count(),
                'totalItems' => count($items),
                'scoredItems' => $scored->count(),
            ];
        }

        [$districts, $facilities] = $this->scopedDropdowns();

        return Inertia::render('Reports/ToolAnalysis', [
            'tools' => Tool::where('slug', '!=', 'counselling')->orderBy('sort_order')->get(['id', 'label']),
            'districts' => $districts,
            'facilities' => $facilities,
            'filters' => $request->only(['tool_id', 'district_id', 'facility_id']),
            'selectedTool' => $selectedTool
                ? ['id' => $selectedTool->id, 'slug' => $selectedTool->slug, 'label' => $selectedTool->label]
                : null,
            'summary' => $summary,
            'items' => $items,
        ]);
    }

    /**
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, array<string, mixed>>}
     */
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
