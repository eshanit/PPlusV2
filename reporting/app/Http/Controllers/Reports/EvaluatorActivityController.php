<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class EvaluatorActivityController extends Controller
{
    private function baseWhere(): string
    {
        $user = auth()->user();

        if (! $user || $user->isAdmin() || ! $user->district_id) {
            return '1=1';
        }

        return "es.district_id = {$user->district_id}";
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
        $month = $request->input('month');
        $districtId = $request->input('district_id');

        $rows = DB::table('evaluation_sessions as es')
            ->join('users as evaluators', 'evaluators.id', '=', 'es.evaluator_id')
            ->join('tools', 'tools.id', '=', 'es.tool_id')
            ->leftJoin('v_session_averages as sa', 'sa.session_id', '=', 'es.id')
            ->whereRaw($this->baseWhere())
            ->where('tools.slug', '!=', 'counselling')
            ->when($month, fn ($q) => $q->whereRaw('DATE_FORMAT(es.eval_date, "%Y-%m") = ?', [$month]))
            ->when($districtId, fn ($q) => $q->where('es.district_id', $districtId))
            ->selectRaw('es.evaluator_id')
            ->selectRaw('CONCAT(evaluators.firstname, " ", evaluators.lastname) as evaluator_name')
            ->selectRaw('COUNT(DISTINCT es.id) as session_count')
            ->selectRaw('COUNT(DISTINCT es.evaluation_group_id) as mentee_count')
            ->selectRaw('COUNT(DISTINCT es.tool_id) as tool_count')
            ->selectRaw('ROUND(AVG(sa.avg_mentee_score), 2) as avg_score')
            ->groupBy('es.evaluator_id', 'evaluators.firstname', 'evaluators.lastname')
            ->orderByDesc('session_count')
            ->get()
            ->map(fn (object $r): array => [
                'evaluatorId' => $r->evaluator_id,
                'name' => $r->evaluator_name,
                'sessions' => (int) $r->session_count,
                'mentees' => (int) $r->mentee_count,
                'tools' => (int) $r->tool_count,
                'avgScore' => $r->avg_score !== null ? round((float) $r->avg_score, 2) : null,
            ])
            ->all();

        $availableMonths = DB::table('evaluation_sessions as es')
            ->whereRaw($this->baseWhere())
            ->selectRaw('DATE_FORMAT(es.eval_date, "%Y-%m") as month')
            ->groupByRaw('DATE_FORMAT(es.eval_date, "%Y-%m")')
            ->orderByRaw('DATE_FORMAT(es.eval_date, "%Y-%m") DESC')
            ->pluck('month')
            ->all();

        $districts = $this->scopedDistricts();

        return Inertia::render('Reports/EvaluatorActivity', [
            'rows' => $rows,
            'availableMonths' => $availableMonths,
            'districts' => $districts,
            'filters' => ['month' => $month, 'district_id' => $districtId],
        ]);
    }
}
