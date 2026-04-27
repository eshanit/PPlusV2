<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Http\Requests\CohortProgressRequest;
use App\Services\ReportQueryService;
use App\Services\ToolService;
use Inertia\Inertia;
use Inertia\Response;

class CohortProgressController extends Controller
{
    public function __construct(
        private readonly ReportQueryService $queries,
        private readonly ToolService $tools,
    ) {}

    public function __invoke(CohortProgressRequest $request): Response
    {
        $toolId = $request->input('tool_id');
        $districtId = $request->input('district_id');

        return Inertia::render('Reports/CohortProgress', [
            'rows' => $this->queries->getCohortProgress($toolId, $districtId),
            'tools' => $this->tools->getAllForDropdown(),
            'districts' => $this->tools->getDistrictsForUser(),
            'filters' => ['tool_id' => $toolId, 'district_id' => $districtId],
        ]);
    }
}
