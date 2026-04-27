<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Http\Requests\ScoreTrajectoryRequest;
use App\Services\ReportQueryService;
use App\Services\ToolService;
use Inertia\Inertia;
use Inertia\Response;

class ScoreTrajectoryController extends Controller
{
    public function __construct(
        private readonly ReportQueryService $queries,
        private readonly ToolService $tools,
    ) {}

    public function __invoke(ScoreTrajectoryRequest $request): Response
    {
        $toolId = $request->input('tool_id');
        $groupId = $request->input('group_id');

        $journeys = $toolId ? $this->queries->getJourneysForTool($toolId) : [];
        $trajectory = $groupId ? $this->queries->getSessionAverages($groupId) : [];
        $selectedJourney = $groupId ? $this->buildSelectedJourney($groupId) : null;

        return Inertia::render('Reports/ScoreTrajectory', [
            'tools' => $this->tools->getAllForDropdown(),
            'journeys' => $journeys,
            'trajectory' => $trajectory,
            'selectedJourney' => $selectedJourney,
            'filters' => ['tool_id' => $toolId, 'group_id' => $groupId],
        ]);
    }

    private function buildSelectedJourney(string $groupId): ?array
    {
        $summary = $this->queries->getJourneySummaryData($groupId);

        if (! $summary?->getGroupId()) {
            return null;
        }

        return $summary->toArrayForTrajectory();
    }
}
