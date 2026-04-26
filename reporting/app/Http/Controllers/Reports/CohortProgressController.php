<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class CohortProgressController extends Controller
{
    private function baseWhere(): string
    {
        $user = auth()->user();

        if (! $user || $user->isAdmin() || ! $user->district_id) {
            return '1=1';
        }

        return "sn.district_id = {$user->district_id}";
    }

    private function scopedDistricts()
    {
        $user = auth()->user();
        if ($user && ! $user->isAdmin() && $user->district_id) {
            return collect([]);
        }

        return DB::table('districts')->orderBy('name')->get(['id', 'name'])
            ->map(fn ($d) => ['id' => (int) $d->id, 'name' => $d->name])->all();
    }

    public function __invoke(Request $request): Response
    {
        $toolId = $request->input('tool_id');
        $districtId = $request->input('district_id');

        $rows = DB::table('v_sessions_numbered as sn')
            ->join('v_session_averages as sa', 'sa.session_id', '=', 'sn.id')
            ->join('tools', 'tools.id', '=', 'sn.tool_id')
            ->whereRaw($this->baseWhere())
            ->where('tools.slug', '!=', 'counselling')
            ->when($toolId, fn ($q) => $q->where('sn.tool_id', $toolId))
            ->when($districtId, fn ($q) => $q->where('sn.district_id', $districtId))
            ->selectRaw('sn.session_number')
            ->selectRaw('ROUND(AVG(sa.avg_mentee_score), 2) as avg_score')
            ->selectRaw('COUNT(DISTINCT sn.evaluation_group_id) as journey_count')
            ->groupBy('sn.session_number')
            ->orderBy('sn.session_number')
            ->limit(20)
            ->get()
            ->map(fn (object $r): array => [
                'session' => (int) $r->session_number,
                'avgScore' => (float) $r->avg_score,
                'journeyCount' => (int) $r->journey_count,
            ])
            ->all();

        $tools = DB::table('tools')
            ->where('slug', '!=', 'counselling')
            ->orderBy('sort_order')
            ->get(['id', 'label'])
            ->map(fn ($t) => ['id' => (int) $t->id, 'label' => $t->label])
            ->all();

        return Inertia::render('Reports/CohortProgress', [
            'rows' => $rows,
            'tools' => $tools,
            'districts' => $this->scopedDistricts(),
            'filters' => ['tool_id' => $toolId, 'district_id' => $districtId],
        ]);
    }
}
