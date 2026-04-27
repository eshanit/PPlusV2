<?php

namespace App\Actions;

class CalculateScoreDistribution
{
    /**
     * @param  iterable<object{count_1: int, count_2: int, count_3: int, count_4: int, count_5: int, total: int, avg_score: ?float}>  $rows
     * @return array<int, array{toolId: int, toolLabel: string, toolSlug: string, count1: int, count2: int, count3: int, count4: int, count5: int, total: int, avgScore: ?float, pct1: float, pct2: float, pct3: float, pct4: float, pct5: float}>
     */
    public function run(iterable $rows): array
    {
        $rows = collect($rows);

        return $rows->map(fn (object $row): array => [
            'toolId' => $row->tool_id,
            'toolLabel' => $row->tool_label,
            'toolSlug' => $row->tool_slug,
            'count1' => (int) $row->count_1,
            'count2' => (int) $row->count_2,
            'count3' => (int) $row->count_3,
            'count4' => (int) $row->count_4,
            'count5' => (int) $row->count_5,
            'total' => (int) $row->total,
            'avgScore' => $row->avg_score !== null ? (float) $row->avg_score : null,
            'pct1' => $this->calculatePercentage((int) $row->count_1, (int) $row->total),
            'pct2' => $this->calculatePercentage((int) $row->count_2, (int) $row->total),
            'pct3' => $this->calculatePercentage((int) $row->count_3, (int) $row->total),
            'pct4' => $this->calculatePercentage((int) $row->count_4, (int) $row->total),
            'pct5' => $this->calculatePercentage((int) $row->count_5, (int) $row->total),
        ])->all();
    }

    public function calculateTotals(iterable $tools): array
    {
        $tools = collect($tools);

        return [
            'totalScored' => (int) $tools->sum('total'),
            'totalItems' => $tools->count(),
        ];
    }

    private function calculatePercentage(int $count, int $total): float
    {
        if ($total === 0) {
            return 0.0;
        }

        return round(($count / $total) * 100, 1);
    }
}
