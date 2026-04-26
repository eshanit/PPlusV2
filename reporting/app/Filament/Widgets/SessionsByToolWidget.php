<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class SessionsByToolWidget extends ChartWidget
{
    protected static ?string $heading = 'Sessions by Tool';

    protected static ?int $sort = 3;

    protected function getData(): array
    {
        $data = DB::table('evaluation_sessions as es')
            ->join('tools', 'es.tool_id', '=', 'tools.id')
            ->select('tools.label', DB::raw('count(es.id) as session_count'))
            ->groupBy('tools.label')
            ->orderByDesc('session_count')
            ->get();

        $labels = $data->pluck('label')->toArray();
        $values = $data->pluck('session_count')->toArray();

        return [
            'datasets' => [
                [
                    'label' => 'Sessions',
                    'data' => $values,
                    'backgroundColor' => '#3b82f6',
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
