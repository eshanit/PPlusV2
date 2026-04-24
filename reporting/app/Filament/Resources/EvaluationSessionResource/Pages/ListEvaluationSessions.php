<?php

namespace App\Filament\Resources\EvaluationSessionResource\Pages;

use App\Filament\Resources\EvaluationSessionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListEvaluationSessions extends ListRecords
{
    protected static string $resource = EvaluationSessionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
