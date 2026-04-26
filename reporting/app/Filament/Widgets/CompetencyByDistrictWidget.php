<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class CompetencyByDistrictWidget extends ChartWidget
{
    protected static ?string $heading = 'Competency Rate by District';

    protected static ?int $sort = 4;

    protected function getData(): array
    {
        $data = DB::table('v_evaluation_group_status as vgs')
            ->join('districts', 'vgs.district_id', '=', 'districts.id')
            ->select('districts.name', DB::raw('count(*) as total'), DB::raw('sum(basic_competent) as competent'))
            ->groupBy('districts.name', 'districts.id')
            ->orderByDesc('competent')
            ->get();

        $labels = $data->pluck('name')->toArray();
        $percentages = $data->map(function ($item) {
            return $item->total > 0 ? round(($item->competent / $item->total) * 100, 1) : 0;
        })->toArray();

        return [
            'datasets' => [
                [
                    'label' => 'Competency %',
                    'data' => $percentages,
                    'backgroundColor' => '#10b981',
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
