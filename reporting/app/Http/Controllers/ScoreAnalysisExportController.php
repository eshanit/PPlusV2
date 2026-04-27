<?php

namespace App\Http\Controllers;

use App\Services\ScoreDistributionService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ScoreAnalysisExportController extends Controller
{
    /**
     * Export score distribution data to CSV.
     */
    public function exportCsv(Request $request, ScoreDistributionService $service): StreamedResponse
    {
        $validated = $request->validate([
            'tool_ids' => 'nullable|array|integer',
            'district_ids' => 'nullable|array|integer',
            'facility_ids' => 'nullable|array|integer',
            'from_date' => 'nullable|date_format:Y-m-d',
            'to_date' => 'nullable|date_format:Y-m-d',
        ]);

        $result = $service->getAggregateScoreCounts(
            toolIds: $validated['tool_ids'] ?? null,
            districtIds: $validated['district_ids'] ?? null,
            facilityIds: $validated['facility_ids'] ?? null,
            fromDate: $validated['from_date'] ?? null,
            toDate: $validated['to_date'] ?? null,
        );

        $filename = 'score-distribution-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(
            function () use ($result): void {
                $output = fopen('php://output', 'w');

                fputcsv($output, ['Tool', 'N/A', '1', '2', '3', '4', '5', 'Total']);

                foreach ($result['tools'] as $tool) {
                    fputcsv($output, [
                        $tool['label'],
                        $tool['scores']['null'],
                        $tool['scores']['1'],
                        $tool['scores']['2'],
                        $tool['scores']['3'],
                        $tool['scores']['4'],
                        $tool['scores']['5'],
                        $tool['total'],
                    ]);
                }

                fclose($output);
            },
            $filename,
            [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            ]
        );
    }

    /**
     * Export item-level score distribution to CSV.
     */
    public function exportItemsCsv(Request $request, ScoreDistributionService $service): StreamedResponse
    {
        $validated = $request->validate([
            'tool_id' => 'nullable|integer|exists:tools,id',
            'district_ids' => 'nullable|array|integer',
            'from_date' => 'nullable|date_format:Y-m-d',
            'to_date' => 'nullable|date_format:Y-m-d',
        ]);

        $result = $service->getItemLevelScoreCounts(
            toolId: $validated['tool_id'] ?? null,
            districtIds: $validated['district_ids'] ?? null,
            fromDate: $validated['from_date'] ?? null,
            toDate: $validated['to_date'] ?? null,
        );

        $filename = 'score-distribution-items-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(
            function () use ($result): void {
                $output = fopen('php://output', 'w');

                fputcsv($output, ['Item', 'Title', 'Tool', 'Advanced', 'Critical', '1', '2', '3', '4', '5', 'N/A', 'Total', 'Avg Score']);

                foreach ($result['items'] as $item) {
                    fputcsv($output, [
                        $item['number'],
                        $item['title'],
                        $item['toolLabel'],
                        $item['isAdvanced'] ? 'Yes' : 'No',
                        $item['isCritical'] ? 'Yes' : 'No',
                        $item['scores']['1'],
                        $item['scores']['2'],
                        $item['scores']['3'],
                        $item['scores']['4'],
                        $item['scores']['5'],
                        $item['scores']['null'],
                        $item['total'],
                        $item['avgScore'] ?? 'N/A',
                    ]);
                }

                fclose($output);
            },
            $filename,
            [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            ]
        );
    }
}
