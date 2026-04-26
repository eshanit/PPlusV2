<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\ActiveJourneysWidget;
use App\Filament\Widgets\CompetencyByDistrictWidget;
use App\Filament\Widgets\CompetencyRateWidget;
use App\Filament\Widgets\OpenGapsWidget;
use App\Filament\Widgets\SessionsByToolWidget;
use App\Filament\Widgets\TotalMenteesWidget;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationIcon = 'heroicon-o-home';

    protected static string $routePath = 'dashboard';

    protected static ?string $title = 'Dashboard';

    public function getWidgets(): array
    {
        return [
            TotalMenteesWidget::class,
            ActiveJourneysWidget::class,
            CompetencyRateWidget::class,
            OpenGapsWidget::class,
            SessionsByToolWidget::class,
            CompetencyByDistrictWidget::class,
        ];
    }

    public function getColumns(): int|string|array
    {
        return [
            'md' => 2,
            'lg' => 4,
        ];
    }
}
