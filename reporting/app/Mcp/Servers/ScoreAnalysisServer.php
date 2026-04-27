<?php

namespace App\Mcp\Servers;

use App\Services\ScoreDistributionService;
use Laravel\Mcp\Server;
use Laravel\Mcp\Types\TextContent;
use Laravel\Mcp\Types\Tool;

class ScoreAnalysisServer extends Server
{
    /**
     * Define MCP server metadata.
     */
    public function meta(): array
    {
        return [
            'name' => 'Score Analysis API',
            'version' => '1.0.0',
            'description' => 'Analyze competency score distributions across evaluation tools',
        ];
    }

    /**
     * Define available MCP tools.
     */
    public function tools(): array
    {
        return [
            Tool::make('aggregate_scores')
                ->description('Get aggregated score distribution by tool with optional filters')
                ->inputSchema([
                    'type' => 'object',
                    'properties' => [
                        'tool_ids' => [
                            'type' => 'array',
                            'items' => ['type' => 'integer'],
                            'description' => 'Filter by specific tool IDs',
                        ],
                        'district_ids' => [
                            'type' => 'array',
                            'items' => ['type' => 'integer'],
                            'description' => 'Filter by specific district IDs',
                        ],
                        'facility_ids' => [
                            'type' => 'array',
                            'items' => ['type' => 'integer'],
                            'description' => 'Filter by specific facility IDs',
                        ],
                        'from_date' => [
                            'type' => 'string',
                            'format' => 'date',
                            'description' => 'Start date for filtering sessions (YYYY-MM-DD)',
                        ],
                        'to_date' => [
                            'type' => 'string',
                            'format' => 'date',
                            'description' => 'End date for filtering sessions (YYYY-MM-DD)',
                        ],
                    ],
                ]),

            Tool::make('item_level_scores')
                ->description('Get item-level score distribution for detailed analysis')
                ->inputSchema([
                    'type' => 'object',
                    'properties' => [
                        'tool_id' => [
                            'type' => 'integer',
                            'description' => 'Filter by specific tool ID',
                        ],
                        'district_ids' => [
                            'type' => 'array',
                            'items' => ['type' => 'integer'],
                            'description' => 'Filter by specific district IDs',
                        ],
                        'from_date' => [
                            'type' => 'string',
                            'format' => 'date',
                            'description' => 'Start date for filtering sessions (YYYY-MM-DD)',
                        ],
                        'to_date' => [
                            'type' => 'string',
                            'format' => 'date',
                            'description' => 'End date for filtering sessions (YYYY-MM-DD)',
                        ],
                    ],
                ]),

            Tool::make('score_gaps_analysis')
                ->description('Analyze competency gaps based on low score frequencies')
                ->inputSchema([
                    'type' => 'object',
                    'properties' => [
                        'tool_id' => [
                            'type' => 'integer',
                            'description' => 'Analyze gaps for specific tool ID',
                        ],
                        'low_score_threshold' => [
                            'type' => 'integer',
                            'default' => 2,
                            'description' => 'Scores at or below this value are considered low (default: 2)',
                        ],
                        'low_score_percentage_threshold' => [
                            'type' => 'number',
                            'default' => 25,
                            'description' => 'Items with low scores above this percentage are flagged (default: 25%)',
                        ],
                    ],
                ]),
        ];
    }

    /**
     * Handle tool execution.
     */
    public function executeToolCall(string $toolName, array $arguments): TextContent
    {
        $service = app(ScoreDistributionService::class);

        return match ($toolName) {
            'aggregate_scores' => $this->handleAggregateScores($service, $arguments),
            'item_level_scores' => $this->handleItemLevelScores($service, $arguments),
            'score_gaps_analysis' => $this->handleScoreGapsAnalysis($service, $arguments),
            default => TextContent::make("Unknown tool: {$toolName}"),
        };
    }

    private function handleAggregateScores(ScoreDistributionService $service, array $arguments): TextContent
    {
        $result = $service->getAggregateScoreCounts(
            toolIds: $arguments['tool_ids'] ?? null,
            districtIds: $arguments['district_ids'] ?? null,
            facilityIds: $arguments['facility_ids'] ?? null,
            fromDate: $arguments['from_date'] ?? null,
            toDate: $arguments['to_date'] ?? null,
        );

        $output = "# Score Distribution Summary\n\n";
        $output .= "**Metadata:**\n";
        $output .= "- Total Sessions: {$result['metadata']['totalSessions']}\n";
        $output .= "- Unique Mentees: {$result['metadata']['uniqueMentees']}\n";
        $output .= '- Tools Analyzed: '.count($result['tools'])."\n\n";

        $output .= "## Tool-Level Distribution\n\n";
        $output .= "| Tool | N/A | 1 | 2 | 3 | 4 | 5 | Total |\n";
        $output .= "|------|-----|---|---|---|---|---|-------|\n";

        foreach ($result['tools'] as $tool) {
            $output .= "| {$tool['label']} ";
            $output .= "| {$tool['scores']['null']} ";
            $output .= "| {$tool['scores']['1']} ";
            $output .= "| {$tool['scores']['2']} ";
            $output .= "| {$tool['scores']['3']} ";
            $output .= "| {$tool['scores']['4']} ";
            $output .= "| {$tool['scores']['5']} ";
            $output .= "| {$tool['total']} |\n";
        }

        return TextContent::make($output);
    }

    private function handleItemLevelScores(ScoreDistributionService $service, array $arguments): TextContent
    {
        $result = $service->getItemLevelScoreCounts(
            toolId: $arguments['tool_id'] ?? null,
            districtIds: $arguments['district_ids'] ?? null,
            fromDate: $arguments['from_date'] ?? null,
            toDate: $arguments['to_date'] ?? null,
        );

        $output = "# Item-Level Score Distribution\n\n";
        $output .= "| Item | Title | Avg Score | 1 | 2 | 3 | 4 | 5 | Total |\n";
        $output .= "|------|-------|-----------|---|---|---|---|---|-------|\n";

        foreach ($result['items'] as $item) {
            $avgStr = $item['avgScore'] ? number_format($item['avgScore'], 2) : 'N/A';
            $output .= "| {$item['number']} ";
            $output .= "| {$item['title']} ";
            $output .= "| {$avgStr} ";
            $output .= "| {$item['scores']['1']} ";
            $output .= "| {$item['scores']['2']} ";
            $output .= "| {$item['scores']['3']} ";
            $output .= "| {$item['scores']['4']} ";
            $output .= "| {$item['scores']['5']} ";
            $output .= "| {$item['total']} |\n";
        }

        return TextContent::make($output);
    }

    private function handleScoreGapsAnalysis(ScoreDistributionService $service, array $arguments): TextContent
    {
        $toolId = $arguments['tool_id'] ?? null;
        $threshold = $arguments['low_score_threshold'] ?? 2;
        $percentThreshold = $arguments['low_score_percentage_threshold'] ?? 25;

        $result = $service->getItemLevelScoreCounts(toolId: $toolId);

        $gaps = [];
        foreach ($result['items'] as $item) {
            $lowScoreCount = ($item['scores']['1'] ?? 0) + ($item['scores']['2'] ?? 0);
            $lowScorePercentage = $item['total'] > 0 ? ($lowScoreCount / $item['total']) * 100 : 0;

            if ($lowScorePercentage >= $percentThreshold) {
                $gaps[] = [
                    'itemNumber' => $item['number'],
                    'title' => $item['title'],
                    'lowScorePercentage' => round($lowScorePercentage, 1),
                    'lowScoreCount' => $lowScoreCount,
                    'total' => $item['total'],
                ];
            }
        }

        // Sort by lowest percentage descending
        usort($gaps, fn ($a, $b) => $b['lowScorePercentage'] <=> $a['lowScorePercentage']);

        $output = "# Competency Gaps Analysis\n\n";
        $output .= "**Criteria:** Items with ≤$threshold scores in ≥$percentThreshold% of evaluations\n\n";
        $output .= '## Flagged Items ('.count($gaps)." found)\n\n";

        if (count($gaps) === 0) {
            $output .= "✓ No significant gaps detected!\n";
        } else {
            $output .= "| Item | Title | Low % | Count |\n";
            $output .= "|------|-------|-------|-------|\n";

            foreach ($gaps as $gap) {
                $output .= "| {$gap['itemNumber']} ";
                $output .= "| {$gap['title']} ";
                $output .= "| {$gap['lowScorePercentage']}% ";
                $output .= "| {$gap['lowScoreCount']}/{$gap['total']} |\n";
            }
        }

        return TextContent::make($output);
    }
}
