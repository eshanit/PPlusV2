<?php

namespace App\Services;

use App\Data\JourneySummaryData;
use Illuminate\Support\Facades\DB;

class ReportQueryService
{
    public function __construct(
        private readonly ReportScopeService $scope,
    ) {}

    /**
     * Per-session figures for a journey. A session's own average covers only the
     * items evaluated that day, so it is returned with its item count; the
     * standing fields (from v_journey_progress) use each item's latest score so
     * far and are the comparable measure from session to session.
     */
    public function getSessionAverages(string $groupId): array
    {
        return DB::table('v_sessions_numbered as sn')
            ->join('v_session_averages as sa', 'sa.session_id', '=', 'sn.id')
            ->leftJoin('v_journey_progress as jp', 'jp.session_id', '=', 'sn.id')
            ->where('sn.evaluation_group_id', $groupId)
            ->orderBy('sn.session_number')
            ->get([
                'sn.id as session_id',
                'sn.session_number',
                'sn.eval_date',
                'sn.phase',
                'sa.avg_mentee_score',
                'sa.scored_items',
                'sa.na_items',
                'jp.required_items',
                'jp.required_achieved',
                'jp.total_items',
                'jp.total_achieved',
                'jp.items_evaluated',
                'jp.current_avg_score',
            ])
            ->map(fn (object $s): array => [
                'sessionId' => $s->session_id,
                'session' => (int) $s->session_number,
                'date' => $s->eval_date,
                'phase' => $s->phase,
                'avgScore' => $s->avg_mentee_score !== null ? round((float) $s->avg_mentee_score, 2) : null,
                'scoredItems' => (int) $s->scored_items,
                'naItems' => (int) $s->na_items,
                ...self::standing($s),
            ])
            ->all();
    }

    /**
     * Standing fields from a v_journey_progress (or v_journey_summary) row.
     *
     * @return array{requiredItems: int, requiredAchieved: int, totalItems: int, totalAchieved: int, itemsEvaluated: int, currentAvgScore: float|null, achievedPct: int|null}
     */
    public static function standing(object $row): array
    {
        $required = (int) ($row->required_items ?? 0);
        $achieved = (int) ($row->required_achieved ?? 0);

        return [
            'requiredItems' => $required,
            'requiredAchieved' => $achieved,
            'totalItems' => (int) ($row->total_items ?? 0),
            'totalAchieved' => (int) ($row->total_achieved ?? 0),
            'itemsEvaluated' => (int) ($row->items_evaluated ?? 0),
            'currentAvgScore' => isset($row->current_avg_score) ? round((float) $row->current_avg_score, 2) : null,
            'achievedPct' => $required > 0 ? (int) round($achieved / $required * 100) : null,
        ];
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
                'intra_day_items',
                'prev_eval_date',
                'day_over_day_delta',
                'day_over_day_items',
            ])
            ->map(fn (object $d): array => [
                'date' => $d->eval_date,
                'roundsThatDay' => (int) $d->rounds_that_day,
                'dayAvgScore' => $d->day_avg_score !== null ? round((float) $d->day_avg_score, 2) : null,
                'firstRoundAvg' => $d->first_round_avg !== null ? round((float) $d->first_round_avg, 2) : null,
                'lastRoundAvg' => $d->last_round_avg !== null ? round((float) $d->last_round_avg, 2) : null,
                'intraDayDelta' => $d->intra_day_delta !== null ? round((float) $d->intra_day_delta, 2) : null,
                'intraDayItems' => (int) $d->intra_day_items,
                'prevDate' => $d->prev_eval_date,
                'dayOverDayDelta' => $d->day_over_day_delta !== null ? round((float) $d->day_over_day_delta, 2) : null,
                'dayOverDayItems' => (int) $d->day_over_day_items,
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

    /**
     * Cohort standing by session number: across journeys, the average of each
     * journey's standing after its Nth session (latest score per item), and the
     * average share of required items achieved by then. Session averages are not
     * used: each covers a different set of items.
     */
    public function getCohortProgress(?int $toolId = null, ?string $districtId = null): array
    {
        return DB::table('v_sessions_numbered as sn')
            ->join('v_journey_progress as jp', 'jp.session_id', '=', 'sn.id')
            ->join('tools', 'tools.id', '=', 'sn.tool_id')
            ->whereRaw(...$this->scope->scope('sn'))
            ->where('tools.slug', '!=', 'counselling')
            ->when($toolId, fn ($q) => $q->where('sn.tool_id', $toolId))
            ->when($districtId && $this->canFilterDistrict(), fn ($q) => $q->where('sn.district_id', $districtId))
            ->selectRaw('sn.session_number')
            ->selectRaw('ROUND(COALESCE(AVG(jp.current_avg_score), 0), 2) as avg_score')
            ->selectRaw('ROUND(AVG(CASE WHEN jp.required_items > 0 THEN jp.required_achieved * 100.0 / jp.required_items END), 0) as achieved_pct')
            ->selectRaw('COUNT(DISTINCT sn.evaluation_group_id) as journey_count')
            ->groupBy('sn.session_number')
            ->orderBy('sn.session_number')
            ->limit(20)
            ->get()
            ->map(fn (object $r): array => [
                'session' => (int) $r->session_number,
                'avgScore' => (float) $r->avg_score,
                'achievedPct' => $r->achieved_pct !== null ? (int) $r->achieved_pct : null,
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
        return DB::table('v_session_item_scores as sis')
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

        return $user && ($user->isAdmin() || $user->isEvaluator());
    }
}
