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

class HighRiskAlertsController extends Controller
{
    public function __construct(private readonly ReportScopeService $scope) {}

    public function __invoke(Request $request): Response
    {
        $alerts = DB::table('v_latest_item_scores as vlis')
            ->join('evaluation_items as ei', 'ei.id', '=', 'vlis.item_id')
            ->join('tool_categories as tc', 'tc.id', '=', 'ei.category_id')
            ->join('tools as t', 't.id', '=', 'ei.tool_id')
            ->join('users as u', 'u.id', '=', 'vlis.mentee_id')
            ->leftJoin('facilities as f', 'f.id', '=', 'vlis.facility_id')
            ->leftJoin('districts as d', 'd.id', '=', 'vlis.district_id')
            ->where('ei.is_critical', true)
            ->whereIn('vlis.mentee_score', [1, 2])
            ->where('t.slug', '!=', 'counselling')
            ->whereRaw(...$this->scope->scope('vlis'))
            ->when($request->tool_id, fn ($q) => $q->where('ei.tool_id', $request->tool_id))
            ->when($request->district_id, fn ($q) => $q->where('vlis.district_id', $request->district_id))
            ->when($request->facility_id, fn ($q) => $q->where('vlis.facility_id', $request->facility_id))
            ->select([
                'vlis.evaluation_group_id',
                'ei.id as item_id',
                'ei.number as item_number',
                'ei.title as item_title',
                'tc.name as category',
                't.id as tool_id',
                't.label as tool_label',
                'vlis.mentee_score as latest_score',
                'vlis.score_date',
                'u.firstname',
                'u.lastname',
                'f.name as facility',
                'd.name as district',
            ])
            ->orderBy('vlis.mentee_score')
            ->orderBy('vlis.score_date')
            ->get()
            ->map(fn (object $row): array => [
                'evaluationGroupId' => $row->evaluation_group_id,
                'mentee' => trim("{$row->firstname} {$row->lastname}"),
                'facility' => $row->facility,
                'district' => $row->district,
                'itemId' => $row->item_id,
                'itemNumber' => $row->item_number,
                'itemTitle' => $row->item_title,
                'category' => $row->category,
                'tool' => $row->tool_label,
                'toolId' => $row->tool_id,
                'latestScore' => (int) $row->latest_score,
                'scoreDate' => $row->score_date,
            ])
            ->all();

        $alertCollection = collect($alerts);
        $totalCriticalItems = DB::table('evaluation_items')->where('is_critical', true)->count();

        $summary = [
            'total' => $alertCollection->count(),
            'score1Count' => $alertCollection->filter(fn ($a) => $a['latestScore'] === 1)->count(),
            'score2Count' => $alertCollection->filter(fn ($a) => $a['latestScore'] === 2)->count(),
            'affectedMentees' => $alertCollection->pluck('mentee')->unique()->count(),
            'totalCriticalItems' => $totalCriticalItems,
        ];

        return Inertia::render('Reports/HighRiskAlerts', [
            'alerts' => $alerts,
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
