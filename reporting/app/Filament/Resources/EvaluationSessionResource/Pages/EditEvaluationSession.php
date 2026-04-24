<?php

namespace App\Filament\Resources\EvaluationSessionResource\Pages;

use App\Filament\Resources\EvaluationSessionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditEvaluationSession extends EditRecord
{
    protected static string $resource = EvaluationSessionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
