<?php

namespace App\Http\Controllers\Reports;

use App\Actions\CalculateScoreDistribution;
use App\Http\Controllers\Controller;
use App\Services\ReportQueryService;
use Inertia\Inertia;
use Inertia\Response;

class ToolScoreDistributionController extends Controller
{
    public function __construct(
        private readonly ReportQueryService $queries,
        private readonly CalculateScoreDistribution $distribution,
    ) {}

    public function __invoke(): Response
    {
        $rows = $this->queries->getScoreDistributionByTool();
        $tools = $this->distribution->run($rows);
        $totals = $this->distribution->calculateTotals($tools);

        return Inertia::render('Reports/ToolScoreDistribution', [
            'tools' => $tools,
            'totalScored' => $totals['totalScored'],
            'totalItems' => $totals['totalItems'],
        ]);
    }
}
