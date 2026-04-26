<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\District;
use App\Models\JourneySummary;
use App\Models\Tool;
use App\Services\ReportScopeService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class JourneyStatusController extends Controller
{
    public function __construct(private readonly ReportScopeService $scope) {}

    public function __invoke(Request $request): Response
    {
        $query = JourneySummary::query()
            ->whereRaw(...$this->scope->scope('v_journey_summary'))
            ->when($request->tool_id, fn ($q) => $q->where('tool_id', $request->tool_id))
            ->when($request->district_id, fn ($q) => $q->where('district_id', $request->district_id))
            ->when($request->status, fn ($q) => $q->where('competency_status', $request->status))
            ->orderBy('latest_session_date', 'desc');

        $paginator = $query->paginate(25)->withQueryString();
        $journeys = $paginator->map(fn (JourneySummary $j): array => [
            'evaluationGroupId' => $j->evaluation_group_id,
            'mentee' => trim("{$j->mentee_firstname} {$j->mentee_lastname}"),
            'tool' => $j->tool_label,
            'district' => $j->district_name,
            'facility' => $j->facility_name,
            'totalSessions' => $j->total_sessions,
            'latestAvgScore' => $j->latest_avg_score,
            'status' => $j->competency_status,
            'sessionsToBasic' => $j->sessions_to_basic_competence,
            'daysToBasic' => $j->days_to_basic_competence,
            'latestSessionDate' => $j->latest_session_date?->toDateString(),
            'openGaps' => $j->open_gaps,
        ]);

        $districtsQuery = District::orderBy('name');
        $user = auth()->user();
        if ($user && ! $user->isAdmin() && $user->district_id) {
            $districtsQuery->where('id', $user->district_id);
        }

        return Inertia::render('Reports/JourneyStatus', [
            'journeys' => $journeys,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'links' => $paginator->linkCollection()->toArray(),
            ],
            'tools' => Tool::where('slug', '!=', 'counselling')
                ->orderBy('sort_order')
                ->get(['id', 'label']),
            'districts' => $districtsQuery->get(['id', 'name']),
            'filters' => $request->only(['tool_id', 'district_id', 'status']),
        ]);
    }
}
