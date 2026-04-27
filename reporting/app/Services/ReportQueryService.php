<?php

namespace App\Services;

use App\Data\JourneySummaryData;
use Illuminate\Support\Facades\DB;

class ReportQueryService
{
    public function __construct(
        private readonly ReportScopeService $scope,
    ) {}

    public function getSessionAverages(string $groupId): array
    {
        return DB::table('v_sessions_numbered as sn')
            ->join('v_session_averages as sa', 'sa.session_id', '=', 'sn.id')
            ->where('sn.evaluation_group_id', $groupId)
            ->orderBy('sn.session_number')
            ->get([
                'sn.id as session_id',
                'sn.session_number',
                'sn.eval_date',
                'sn.phase',
                'sa.avg_mentee_score',
                'sa.scored_items',
            ])
            ->map(fn (object $s): array => [
                'sessionId' => $s->session_id,
                'session' => (int) $s->session_number,
                'date' => $s->eval_date,
                'phase' => $s->phase,
                'avgScore' => $s->avg_mentee_score !== null ? round((float) $s->avg_mentee_score, 2) : null,
                'scoredItems' => (int) $s->scored_items,
            ])
            ->all();
    }

    public function getCohortProgress(?int $toolId = null, ?int $districtId = null): array
    {
        return DB::table('v_sessions_numbered as sn')
            ->join('v_session_averages as sa', 'sa.session_id', '=', 'sn.id')
            ->join('tools', 'tools.id', '=', 'sn.tool_id')
            ->whereRaw(...$this->scope->scope('sn'))
            ->where('tools.slug', '!=', 'counselling')
            ->when($toolId, fn ($q) => $q->where('sn.tool_id', $toolId))
            ->when($districtId && $this->canFilterDistrict(), fn ($q) => $q->where('sn.district_id', $districtId))
            ->selectRaw('sn.session_number')
            ->selectRaw('ROUND(COALESCE(AVG(sa.avg_mentee_score), 0), 2) as avg_score')
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
    }

    public function getJourneysForTool(int $toolId): array
    {
        return DB::table('v_journey_summary')
            ->whereRaw(...$this->scope->scope('v_journey_summary'))
            ->where('tool_id', $toolId)
            ->orderBy('mentee_lastname')
            ->orderBy('mentee_firstname')
            ->get(['evaluation_group_id', 'mentee_firstname', 'mentee_lastname', 'total_sessions', 'competency_status'])
            ->map(fn (object $j): array => [
                'groupId' => $j->evaluation_group_id,
                'label' => trim("{$j->mentee_firstname} {$j->mentee_lastname}")." ({$j->total_sessions} sessions)",
                'status' => $j->competency_status,
            ])
            ->all();
    }

    public function getJourneySummary(string $groupId): ?object
    {
        return DB::table('v_journey_summary')
            ->whereRaw(...$this->scope->scope('v_journey_summary'))
            ->where('evaluation_group_id', $groupId)
            ->first();
    }

    public function getJourneySummaryData(string $groupId): ?JourneySummaryData
    {
        return new JourneySummaryData($this->getJourneySummary($groupId));
    }

    public function getScoreDistributionByTool(): array
    {
        return DB::table('session_item_scores as sis')
            ->join('evaluation_sessions as es', 'es.id', '=', 'sis.session_id')
            ->join('evaluation_items as ei', 'ei.id', '=', 'sis.item_id')
            ->join('tools as t', 't.id', '=', 'ei.tool_id')
            ->where('t.slug', '!=', 'counselling')
            ->whereNotNull('sis.mentee_score')
            ->whereRaw(...$this->scope->scope('es'))
            ->select([
                't.id as tool_id',
                't.label as tool_label',
                't.slug as tool_slug',
            ])
            ->selectRaw('COUNT(CASE WHEN sis.mentee_score = 1 THEN 1 END) as count_1')
            ->selectRaw('COUNT(CASE WHEN sis.mentee_score = 2 THEN 1 END) as count_2')
            ->selectRaw('COUNT(CASE WHEN sis.mentee_score = 3 THEN 1 END) as count_3')
            ->selectRaw('COUNT(CASE WHEN sis.mentee_score = 4 THEN 1 END) as count_4')
            ->selectRaw('COUNT(CASE WHEN sis.mentee_score = 5 THEN 1 END) as count_5')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('ROUND(AVG(sis.mentee_score), 2) as avg_score')
            ->groupBy('t.id', 't.label', 't.slug')
            ->orderBy('t.sort_order')
            ->get()
            ->all();
    }

    private function canFilterDistrict(): bool
    {
        $user = auth()->user();

        return $user && $user->isAdmin();
    }
}
