<?php

namespace App\Http\Controllers;

use App\Services\ReportScopeService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ReportingDashboardController extends Controller
{
    private const CACHE_TTL = 300; // 5 minutes

    public function __construct(private readonly ReportScopeService $scope) {}

    public function __invoke(): Response
    {
        if (! $this->analyticsSchemaReady()) {
            return Inertia::render('Dashboard', $this->emptyDashboardData());
        }

        return Inertia::render('Dashboard', [
            'summary' => $this->summary(),
            'toolProgress' => $this->toolProgress(),
            'facilityProgress' => $this->facilityProgress(),
            'recentCompletions' => $this->recentCompletions(),
            'activeJourneys' => $this->activeJourneys(),
            'gapSummary' => $this->gapSummary(),
        ]);
    }

    private function analyticsSchemaReady(): bool
    {
        try {
            DB::table('v_evaluation_group_status')->limit(1)->exists();
            DB::table('gap_entries')->limit(1)->exists();
        } catch (QueryException) {
            return false;
        }

        return true;
    }

    private function cacheKey(string $suffix): string
    {
        $userId = auth()->id() ?? 'guest';
        $districtId = auth()->user()?->district_id ?? 'none';

        return "report:dashboard:{$suffix}:user{$userId}:district{$districtId}";
    }

    private function cached(array|string $key, callable $fallback, ?int $ttl = null): array
    {
        $key = is_array($key) ? $this->cacheKey($key[0]) : $this->cacheKey($key);
        $ttl = $ttl ?? self::CACHE_TTL;

        return Cache::remember($key, $ttl, $fallback);
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyDashboardData(): array
    {
        return [
            'summary' => [
                'totalJourneys' => 0,
                'basicComplete' => 0,
                'fullComplete' => 0,
                'activeJourneys' => 0,
                'basicCompletionRate' => 0.0,
                'averageSessionsToBasic' => null,
                'averageDaysToBasic' => null,
                'openGaps' => 0,
            ],
            'toolProgress' => [],
            'facilityProgress' => [],
            'recentCompletions' => [],
            'activeJourneys' => [],
            'gapSummary' => [
                'total' => 0,
                'open' => 0,
                'resolved' => 0,
                'coveredNow' => 0,
                'coveringLater' => 0,
                'supervisionLevels' => [],
            ],
        ];
    }

    /**
     * @return array<string, int|float>
     */
    private function summary(): array
    {
        return $this->cached('summary', function () {
            $base = DB::table('v_evaluation_group_status as vgs')
                ->whereRaw(...$this->scope->scope('vgs'));

            $totalJourneys = (clone $base)->count();
            $basicComplete = (clone $base)->whereNotNull('sessions_to_basic_competence')->count();
            $fullComplete = (clone $base)->whereNotNull('sessions_to_full_competence')->count();
            $activeJourneys = (clone $base)->whereNull('sessions_to_basic_competence')->count();

            return [
                'totalJourneys' => $totalJourneys,
                'basicComplete' => $basicComplete,
                'fullComplete' => $fullComplete,
                'activeJourneys' => $activeJourneys,
                'basicCompletionRate' => $this->rate($basicComplete, $totalJourneys),
                'averageSessionsToBasic' => $this->average('sessions_to_basic_competence'),
                'averageDaysToBasic' => $this->average('days_to_basic_competence'),
                'openGaps' => DB::table('gap_entries')
                    ->whereRaw(...$this->scope->gapScope())
                    ->whereNull('resolved_at')
                    ->count(),
            ];
        });
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function toolProgress(): array
    {
        return $this->cached('tool_progress', function () {
            return DB::table('v_evaluation_group_status as status')
                ->join('tools', 'tools.id', '=', 'status.tool_id')
                ->whereRaw(...$this->scope->scope('status'))
                ->select([
                    'tools.id',
                    'tools.slug',
                    'tools.label',
                ])
                ->selectRaw('COUNT(*) as total_journeys')
                ->selectRaw('SUM(CASE WHEN status.sessions_to_basic_competence IS NOT NULL THEN 1 ELSE 0 END) as basic_complete')
                ->selectRaw('SUM(CASE WHEN status.sessions_to_full_competence IS NOT NULL THEN 1 ELSE 0 END) as full_complete')
                ->selectRaw('ROUND(AVG(status.sessions_to_basic_competence), 1) as avg_sessions_to_basic')
                ->selectRaw('ROUND(AVG(status.days_to_basic_competence), 1) as avg_days_to_basic')
                ->groupBy('tools.id', 'tools.slug', 'tools.label', 'tools.sort_order')
                ->orderBy('tools.sort_order')
                ->get()
                ->map(fn (object $row): array => [
                    'id' => $row->id,
                    'slug' => $row->slug,
                    'label' => $row->label,
                    'totalJourneys' => (int) $row->total_journeys,
                    'basicComplete' => (int) $row->basic_complete,
                    'fullComplete' => (int) $row->full_complete,
                    'completionRate' => $this->rate((int) $row->basic_complete, (int) $row->total_journeys),
                    'averageSessionsToBasic' => $this->nullableFloat($row->avg_sessions_to_basic),
                    'averageDaysToBasic' => $this->nullableFloat($row->avg_days_to_basic),
                ])
                ->all();
        });
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function facilityProgress(): array
    {
        return $this->cached('facility_progress', function () {
            return DB::table('v_evaluation_group_status as status')
                ->leftJoin('facilities', 'facilities.id', '=', 'status.facility_id')
                ->leftJoin('districts', 'districts.id', '=', 'status.district_id')
                ->whereRaw(...$this->scope->scope('status'))
                ->select([
                    'facilities.id',
                    'facilities.name',
                    'districts.name as district_name',
                ])
                ->selectRaw('COUNT(*) as total_journeys')
                ->selectRaw('SUM(CASE WHEN status.sessions_to_basic_competence IS NOT NULL THEN 1 ELSE 0 END) as basic_complete')
                ->selectRaw('ROUND(AVG(status.days_to_basic_competence), 1) as avg_days_to_basic')
                ->groupBy('facilities.id', 'facilities.name', 'districts.name')
                ->orderByDesc('total_journeys')
                ->limit(8)
                ->get()
                ->map(fn (object $row): array => [
                    'id' => $row->id,
                    'name' => $row->name ?? 'Unassigned',
                    'district' => $row->district_name ?? '—',
                    'totalJourneys' => (int) $row->total_journeys,
                    'basicComplete' => (int) $row->basic_complete,
                    'completionRate' => $this->rate((int) $row->basic_complete, (int) $row->total_journeys),
                    'averageDaysToBasic' => $this->nullableFloat($row->avg_days_to_basic),
                ])
                ->all();
        });
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function recentCompletions(): array
    {
        return $this->cached('recent_completions', function () {
            return DB::table('v_evaluation_group_status as status')
                ->join('users as mentees', 'mentees.id', '=', 'status.mentee_id')
                ->join('tools', 'tools.id', '=', 'status.tool_id')
                ->leftJoin('facilities', 'facilities.id', '=', 'status.facility_id')
                ->leftJoin('districts', 'districts.id', '=', 'status.district_id')
                ->whereRaw(...$this->scope->scope('status'))
                ->whereNotNull('status.basic_competent_at')
                ->orderByDesc('status.basic_competent_at')
                ->limit(8)
                ->get([
                    'status.evaluation_group_id',
                    'status.basic_competent_at',
                    'status.sessions_to_basic_competence',
                    'status.days_to_basic_competence',
                    'mentees.firstname',
                    'mentees.lastname',
                    'tools.label as tool',
                    'facilities.name as facility',
                    'districts.name as district',
                ])
                ->map(fn (object $row): array => [
                    'evaluationGroupId' => $row->evaluation_group_id,
                    'mentee' => trim("{$row->firstname} {$row->lastname}"),
                    'tool' => $row->tool,
                    'facility' => $row->facility,
                    'district' => $row->district,
                    'completedAt' => $row->basic_competent_at,
                    'sessionsToBasic' => (int) $row->sessions_to_basic_competence,
                    'daysToBasic' => $row->days_to_basic_competence === null ? null : (int) $row->days_to_basic_competence,
                ])
                ->all();
        });
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function activeJourneys(): array
    {
        return $this->cached('active_journeys', function () {
            return DB::table('v_evaluation_group_status as status')
                ->join('users as mentees', 'mentees.id', '=', 'status.mentee_id')
                ->join('tools', 'tools.id', '=', 'status.tool_id')
                ->leftJoin('facilities', 'facilities.id', '=', 'status.facility_id')
                ->whereRaw(...$this->scope->scope('status'))
                ->whereNull('status.sessions_to_basic_competence')
                ->orderByDesc('status.latest_session_date')
                ->limit(8)
                ->get([
                    'status.evaluation_group_id',
                    'status.total_sessions',
                    'status.latest_session_date',
                    'mentees.firstname',
                    'mentees.lastname',
                    'tools.label as tool',
                    'facilities.name as facility',
                ])
                ->map(fn (object $row): array => [
                    'evaluationGroupId' => $row->evaluation_group_id,
                    'mentee' => trim("{$row->firstname} {$row->lastname}"),
                    'tool' => $row->tool,
                    'facility' => $row->facility,
                    'totalSessions' => (int) $row->total_sessions,
                    'latestSessionDate' => $row->latest_session_date,
                ])
                ->all();
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function gapSummary(): array
    {
        return $this->cached('gap_summary', function () {
            $total = DB::table('gap_entries')->whereRaw(...$this->scope->gapScope())->count();
            $open = DB::table('gap_entries')->whereRaw(...$this->scope->gapScope())->whereNull('resolved_at')->count();
            $coveredNow = DB::table('gap_entries')
                ->whereRaw(...$this->scope->gapScope())
                ->whereNull('resolved_at')
                ->where('covered_in_mentorship', true)
                ->count();
            $coveringLater = DB::table('gap_entries')
                ->whereRaw(...$this->scope->gapScope())
                ->whereNull('resolved_at')
                ->where('covering_later', true)
                ->count();

            $supervisionLevels = DB::table('gap_entries')
                ->select('supervision_level')
                ->selectRaw('COUNT(*) as total')
                ->whereRaw(...$this->scope->gapScope())
                ->whereNull('resolved_at')
                ->whereNotNull('supervision_level')
                ->groupBy('supervision_level')
                ->orderByDesc('total')
                ->get()
                ->map(fn (object $row): array => [
                    'label' => $this->formatSupervisionLevel($row->supervision_level),
                    'total' => (int) $row->total,
                ])
                ->all();

            return [
                'total' => $total,
                'open' => $open,
                'resolved' => $total - $open,
                'coveredNow' => $coveredNow,
                'coveringLater' => $coveringLater,
                'supervisionLevels' => $supervisionLevels,
            ];
        });
    }

    private function average(string $column): ?float
    {
        return $this->nullableFloat(
            DB::table('v_evaluation_group_status as vgs')
                ->whereRaw(...$this->scope->scope('vgs'))
                ->whereNotNull($column)
                ->avg($column)
        );
    }

    private function nullableFloat(mixed $value): ?float
    {
        return $value === null ? null : round((float) $value, 1);
    }

    private function rate(int $value, int $total): float
    {
        if ($total === 0) {
            return 0.0;
        }

        return round(($value / $total) * 100, 1);
    }

    private function formatSupervisionLevel(?string $value): string
    {
        return match ($value) {
            'intensive_mentorship' => 'Intensive mentorship',
            'ongoing_mentorship' => 'Ongoing mentorship',
            'independent_practice' => 'Independent practice',
            default => 'Unspecified',
        };
    }
}
