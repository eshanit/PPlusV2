<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class ActiveJourneysWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $activeJourneys = DB::table('v_evaluation_group_status')
            ->where(function ($query) {
                $query->where('basic_competent', 0)
                    ->where('fully_competent', 0);
            })
            ->count();

        return [
            Stat::make('Active Journeys', $activeJourneys)
                ->description('Journeys in progress (not competent)')
                ->descriptionIcon('heroicon-m-chart-bar')
                ->color('info'),
        ];
    }
}
