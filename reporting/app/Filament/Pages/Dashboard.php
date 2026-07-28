<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\RecentSyncFailuresWidget;
use App\Filament\Widgets\SyncHealthOverview;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationIcon = 'heroicon-o-home';

    protected static string $routePath = 'dashboard';

    protected static ?string $title = 'Dashboard';

    public function getWidgets(): array
    {
        return [
            SyncHealthOverview::class,
            RecentSyncFailuresWidget::class,
        ];
    }

    public function getColumns(): int|string|array
    {
        return 1;
    }
}
