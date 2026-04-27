<?php

namespace App\Services;

use App\Models\EvaluationSession;
use App\Models\Tool;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ScoreDistributionService
{
    public function __construct(private ReportScopeService $scope) {}

    /**
     * Get aggregated score distribution by tool.
     *
     * Returns an array with tool data including score counts for each tool.
     *
     * @param  array<int>|null  $toolIds  Filter by specific tool IDs
     * @param  array<int>|null  $districtIds  Filter by specific district IDs
     * @param  array<int>|null  $facilityIds  Filter by specific facility IDs
     * @param  string|null  $fromDate  Filter sessions from this date (Y-m-d)
     * @param  string|null  $toDate  Filter sessions until this date (Y-m-d)
     * @return array<string, mixed> Array with 'tools' and 'metadata'
     */
    public function getAggregateScoreCounts(
        ?array $toolIds = null,
        ?array $districtIds = null,
        ?array $facilityIds = null,
        ?string $fromDate = null,
        ?string $toDate = null,
    ): array {
        $query = DB::table('session_item_scores as sis')
            ->join('evaluation_items as ei', 'sis.item_id', '=', 'ei.id')
            ->join('tools as t', 'ei.tool_id', '=', 't.id')
            ->join('evaluation_sessions as es', 'sis.session_id', '=', 'es.id')
            ->whereNotNull('es.synced_at');

        // Apply district scope for non-admin users
        $user = Auth::user();
        if ($user && ! $user->isAdmin() && $user->district_id) {
            $query->where('es.district_id', $user->district_id);
        }

        // Apply filters
        if ($toolIds && count($toolIds) > 0) {
            $query->whereIn('t.id', $toolIds);
        }

        if ($districtIds && count($districtIds) > 0) {
            $query->whereIn('es.district_id', $districtIds);
        }

        if ($facilityIds && count($facilityIds) > 0) {
            $query->whereIn('es.facility_id', $facilityIds);
        }

        if ($fromDate) {
            $query->whereDate('es.eval_date', '>=', $fromDate);
        }

        if ($toDate) {
            $query->whereDate('es.eval_date', '<=', $toDate);
        }

        $data = $query->selectRaw(
            't.id as tool_id, t.label as tool_label, t.sort_order,
            SUM(CASE WHEN sis.mentee_score IS NULL THEN 1 ELSE 0 END) as nulls,
            SUM(CASE WHEN sis.mentee_score = 1 THEN 1 ELSE 0 END) as ones,
            SUM(CASE WHEN sis.mentee_score = 2 THEN 1 ELSE 0 END) as twos,
            SUM(CASE WHEN sis.mentee_score = 3 THEN 1 ELSE 0 END) as threes,
            SUM(CASE WHEN sis.mentee_score = 4 THEN 1 ELSE 0 END) as fours,
            SUM(CASE WHEN sis.mentee_score = 5 THEN 1 ELSE 0 END) as fives,
            COUNT(sis.id) as total'
        )
            ->groupBy('t.id', 't.label', 't.sort_order')
            ->orderBy('t.sort_order')
            ->get();

        // Get metadata
        $sessions = EvaluationSession::whereNotNull('synced_at');
        if ($user && ! $user->isAdmin() && $user->district_id) {
            $sessions->where('district_id', $user->district_id);
        }
        if ($districtIds && count($districtIds) > 0) {
            $sessions->whereIn('district_id', $districtIds);
        }
        if ($facilityIds && count($facilityIds) > 0) {
            $sessions->whereIn('facility_id', $facilityIds);
        }
        if ($fromDate) {
            $sessions->whereDate('eval_date', '>=', $fromDate);
        }
        if ($toDate) {
            $sessions->whereDate('eval_date', '<=', $toDate);
        }

        $totalSessions = $sessions->count();
        $uniqueMentees = $sessions->distinct('mentee_id')->count('mentee_id');

        return [
            'tools' => $data->map(fn ($row) => [
                'toolId' => $row->tool_id,
                'label' => $row->tool_label,
                'sortOrder' => $row->sort_order,
                'scores' => [
                    'null' => (int) $row->nulls,
                    '1' => (int) $row->ones,
                    '2' => (int) $row->twos,
                    '3' => (int) $row->threes,
                    '4' => (int) $row->fours,
                    '5' => (int) $row->fives,
                ],
                'total' => (int) $row->total,
            ])->toArray(),
            'metadata' => [
                'totalSessions' => $totalSessions,
                'uniqueMentees' => $uniqueMentees,
                'totalTools' => Tool::count(),
            ],
        ];
    }

    /**
     * Get item-level score distribution for detailed analysis.
     *
     * @param  int|null  $toolId  Filter by specific tool ID
     * @param  array<int>|null  $districtIds  Filter by specific district IDs
     * @param  string|null  $fromDate  Filter sessions from this date (Y-m-d)
     * @param  string|null  $toDate  Filter sessions until this date (Y-m-d)
     * @return array<string, mixed>
     */
    public function getItemLevelScoreCounts(
        ?int $toolId = null,
        ?array $districtIds = null,
        ?string $fromDate = null,
        ?string $toDate = null,
    ): array {
        $query = DB::table('session_item_scores as sis')
            ->join('evaluation_items as ei', 'sis.item_id', '=', 'ei.id')
            ->join('tools as t', 'ei.tool_id', '=', 't.id')
            ->join('evaluation_sessions as es', 'sis.session_id', '=', 'es.id')
            ->whereNotNull('es.synced_at');

        // Apply district scope for non-admin users
        $user = Auth::user();
        if ($user && ! $user->isAdmin() && $user->district_id) {
            $query->where('es.district_id', $user->district_id);
        }

        if ($toolId) {
            $query->where('t.id', $toolId);
        }

        if ($districtIds && count($districtIds) > 0) {
            $query->whereIn('es.district_id', $districtIds);
        }

        if ($fromDate) {
            $query->whereDate('es.eval_date', '>=', $fromDate);
        }

        if ($toDate) {
            $query->whereDate('es.eval_date', '<=', $toDate);
        }

        $data = $query->selectRaw(
            'ei.id, ei.number, ei.slug, ei.title, ei.is_advanced, ei.is_critical,
            t.label as tool_label,
            SUM(CASE WHEN sis.mentee_score IS NULL THEN 1 ELSE 0 END) as nulls,
            SUM(CASE WHEN sis.mentee_score = 1 THEN 1 ELSE 0 END) as ones,
            SUM(CASE WHEN sis.mentee_score = 2 THEN 1 ELSE 0 END) as twos,
            SUM(CASE WHEN sis.mentee_score = 3 THEN 1 ELSE 0 END) as threes,
            SUM(CASE WHEN sis.mentee_score = 4 THEN 1 ELSE 0 END) as fours,
            SUM(CASE WHEN sis.mentee_score = 5 THEN 1 ELSE 0 END) as fives,
            COUNT(sis.id) as total,
            AVG(sis.mentee_score) as avg_score'
        )
            ->groupBy('ei.id', 'ei.number', 'ei.slug', 'ei.title', 'ei.is_advanced', 'ei.is_critical', 't.label')
            ->orderBy('ei.sort_order')
            ->get();

        return [
            'items' => $data->map(fn ($row) => [
                'itemId' => $row->id,
                'number' => $row->number,
                'slug' => $row->slug,
                'title' => $row->title,
                'toolLabel' => $row->tool_label,
                'isAdvanced' => (bool) $row->is_advanced,
                'isCritical' => (bool) $row->is_critical,
                'scores' => [
                    'null' => (int) $row->nulls,
                    '1' => (int) $row->ones,
                    '2' => (int) $row->twos,
                    '3' => (int) $row->threes,
                    '4' => (int) $row->fours,
                    '5' => (int) $row->fives,
                ],
                'total' => (int) $row->total,
                'avgScore' => $row->avg_score ? round($row->avg_score, 2) : null,
            ])->toArray(),
        ];
    }

    /**
     * Get color class for count-based heatmap intensity.
     */
    public function getCountColorClass(int $count): string
    {
        return match (true) {
            $count === 0 => 'bg-gray-50 text-gray-400',
            $count <= 5 => 'bg-gray-100 text-gray-700',
            $count <= 10 => 'bg-blue-100 text-blue-700',
            $count <= 20 => 'bg-blue-300 text-blue-900',
            $count <= 50 => 'bg-emerald-400 text-white',
            $count <= 100 => 'bg-emerald-600 text-white',
            default => 'bg-emerald-800 text-white font-bold',
        };
    }
}
