<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class TotalMenteesWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $totalMentees = DB::table('evaluation_sessions')
            ->distinct('mentee_id')
            ->count('mentee_id');

        return [
            Stat::make('Total Mentees', $totalMentees)
                ->description('Unique mentees enrolled')
                ->descriptionIcon('heroicon-m-user-group')
                ->color('primary'),
        ];
    }
}
