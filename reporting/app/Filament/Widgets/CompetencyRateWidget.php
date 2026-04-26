<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class CompetencyRateWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $totalJourneys = DB::table('v_evaluation_group_status')->count();

        if ($totalJourneys === 0) {
            $competencyRate = 0;
        } else {
            $competentJourneys = DB::table('v_evaluation_group_status')
                ->where('basic_competent', 1)
                ->count();
            $competencyRate = round(($competentJourneys / $totalJourneys) * 100, 1);
        }

        return [
            Stat::make('Competency Rate', $competencyRate.'%')
                ->description('Journeys with basic competency achieved')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),
        ];
    }
}
