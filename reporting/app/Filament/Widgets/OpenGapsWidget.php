<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class OpenGapsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $openGaps = DB::table('gap_entries')
            ->whereNull('resolved_at')
            ->count();

        return [
            Stat::make('Open Gaps', $openGaps)
                ->description('Unresolved competency gaps')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color('warning'),
        ];
    }
}
