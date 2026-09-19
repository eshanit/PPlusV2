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

    public function getDayProgress(string $groupId): array
    {
        return DB::table('v_day_progress')
            ->where('evaluation_group_id', $groupId)
            ->orderBy('eval_date')
            ->get([
                'eval_date',
                'rounds_that_day',
                'day_avg_score',
                'first_round_avg',
                'last_round_avg',
                'intra_day_delta',
                'prev_eval_date',
                'day_over_day_delta',
            ])
            ->map(fn (object $d): array => [
                'date' => $d->eval_date,
                'roundsThatDay' => (int) $d->rounds_that_day,
                'dayAvgScore' => $d->day_avg_score !== null ? round((float) $d->day_avg_score, 2) : null,
                'firstRoundAvg' => $d->first_round_avg !== null ? round((float) $d->first_round_avg, 2) : null,
                'lastRoundAvg' => $d->last_round_avg !== null ? round((float) $d->last_round_avg, 2) : null,
                'intraDayDelta' => $d->intra_day_delta !== null ? round((float) $d->intra_day_delta, 2) : null,
                'prevDate' => $d->prev_eval_date,
                'dayOverDayDelta' => $d->day_over_day_delta !== null ? round((float) $d->day_over_day_delta, 2) : null,
            ])
            ->all();
    }

    public function getGaps(string $groupId): array
    {
        return DB::table('gap_entries')
            ->where('evaluation_group_id', $groupId)
            ->orderBy('identified_at')
            ->get(['identified_at', 'domains', 'supervision_level', 'resolved_at'])
            ->map(fn (object $g): array => [
                'identifiedAt' => $g->identified_at,
                'domains' => json_decode((string) $g->domains, true) ?? [],
                'supervisionLevel' => $g->supervision_level,
                'isResolved' => $g->resolved_at !== null,
            ])
            ->all();
    }

    public function getCohortProgress(?int $toolId = null, ?string $districtId = null): array
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
            // Advanced (grey) items aren't required for competency, so "% at
            // goal" needs to be computed separately for basic vs. advanced —
            // blending them would make tools with more advanced items look
            // worse for reasons unrelated to mentee performance.
            ->selectRaw('COUNT(CASE WHEN ei.is_advanced = 0 THEN 1 END) as basic_total')
            ->selectRaw('COUNT(CASE WHEN ei.is_advanced = 0 AND sis.mentee_score >= 4 THEN 1 END) as basic_at_goal')
            ->selectRaw('COUNT(CASE WHEN ei.is_advanced = 1 THEN 1 END) as advanced_total')
            ->selectRaw('COUNT(CASE WHEN ei.is_advanced = 1 AND sis.mentee_score >= 4 THEN 1 END) as advanced_at_goal')
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
