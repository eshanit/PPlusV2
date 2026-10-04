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
    private const CRITICAL_ITEM_RATIONALES = [
        'diabetes-D17' => 'Severe hypoglycemia can quickly lead to seizures, loss of consciousness, or death; prompt recognition and stabilization reduce immediate harm.',
        'diabetes-D20' => 'Hyperglycemic emergencies can cause severe dehydration, altered consciousness, and circulatory collapse, so early stabilization and referral are time-critical.',
        'diabetes-D21' => 'Recognizing when hospitalization is needed helps ensure severe hyperglycemia and its complications receive timely higher-level care.',
        'cardiac-C2' => 'Clinical decompensation and worsening volume overload can progress to respiratory distress or poor organ perfusion and require escalation.',
        'cardiac-C12' => 'Identifying heart-failure deterioration and its triggers supports timely treatment and hospitalization before complications worsen.',
        'cardiac-C19' => 'Atrial fibrillation can cause blood clots and embolic stroke; appropriate recognition and anticoagulation decisions help reduce that risk.',
        'sickle_cell-S17' => 'A vaso-occlusive crisis can be severe and may need inpatient pain control, hydration, monitoring, or treatment of complications.',
        'sickle_cell-S18' => 'Acute chest syndrome can rapidly impair oxygenation and become life-threatening, making urgent hospital assessment essential.',
        'sickle_cell-S19' => 'Stroke needs immediate hospital assessment; delays can increase the risk of permanent neurological injury or death.',
        'sickle_cell-S20' => 'Splenic sequestration or severe hemolysis can cause a rapid, dangerous fall in hemoglobin and circulatory instability.',
        'sickle_cell-S21' => 'Severe anemia can compromise oxygen delivery and strain vital organs; urgent recognition and transfusion escalation may be lifesaving.',
        'sickle_cell-S23' => 'Infection can progress rapidly to sepsis in sickle cell disease; timely evaluation and appropriate treatment reduce the risk of severe complications.',
        'respiratory-R19' => 'Severe distress, hypoxia, or altered consciousness may indicate respiratory failure and require immediate referral or hospitalization.',
        'hypertension-H14' => 'Distinguishing urgency from emergency helps identify when acute target-organ injury is present and avoids inappropriate delays or escalation.',
        'hypertension-H15' => 'Acute organ damage can threaten the brain, heart, kidneys, and other vital organs and requires prompt admission and treatment.',
        'hypertension-H17' => 'A hypertensive emergency can cause ongoing acute organ injury; appropriate management and escalation are time-sensitive.',
        'ckd-H11' => 'Hyperkalemia can trigger fatal arrhythmias, while fluid overload and encephalopathy can become life-threatening; urgent admission or dialysis may be needed.',
        'epilepsy-E10' => 'Following a stepwise antiseizure-drug protocol reduces avoidable toxicity and treatment failure, supporting safer seizure control.',
    ];

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
            ->when($request->mentee_id, fn ($q) => $q->where('vlis.mentee_id', $request->mentee_id))
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
        $criticalItems = DB::table('evaluation_items as ei')
            ->join('tools as t', 't.id', '=', 'ei.tool_id')
            ->where('ei.is_critical', true)
            ->where('t.slug', '!=', 'counselling')
            ->orderBy('t.sort_order')
            ->orderBy('ei.sort_order')
            ->get([
                'ei.slug',
                'ei.number',
                'ei.title',
                't.label as tool',
            ])
            ->map(fn (object $item): array => [
                'slug' => $item->slug,
                'number' => $item->number,
                'title' => $item->title,
                'tool' => $item->tool,
                'rationale' => self::CRITICAL_ITEM_RATIONALES[$item->slug]
                    ?? 'This competency was designated as critical for patient safety. Review the competency statement and confirm the rationale with the clinical team.',
            ])
            ->all();

        $summary = [
            'total' => $alertCollection->count(),
            'score1Count' => $alertCollection->filter(fn ($a) => $a['latestScore'] === 1)->count(),
            'score2Count' => $alertCollection->filter(fn ($a) => $a['latestScore'] === 2)->count(),
            'affectedMentees' => $alertCollection->pluck('mentee')->unique()->count(),
            'totalCriticalItems' => count($criticalItems),
        ];

        return Inertia::render('Reports/HighRiskAlerts', [
            'alerts' => $alerts,
            'summary' => $summary,
            'criticalItems' => $criticalItems,
            'tools' => Tool::where('slug', '!=', 'counselling')->orderBy('sort_order')->get(['id', 'label']),
            ...$this->scopedDropdowns(),
            'menteeOptions' => $this->scope->menteeOptions(),
            'filters' => $request->only(['tool_id', 'district_id', 'facility_id', 'mentee_id']),
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
