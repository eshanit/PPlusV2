<?php

namespace App\Filament\Resources\EvaluationSessionResource\Pages;

use App\Filament\Resources\EvaluationSessionResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewEvaluationSession extends ViewRecord
{
    protected static string $resource = EvaluationSessionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
