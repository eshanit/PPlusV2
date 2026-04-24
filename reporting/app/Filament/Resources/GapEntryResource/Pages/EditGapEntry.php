<?php

namespace App\Filament\Resources\GapEntryResource\Pages;

use App\Filament\Resources\GapEntryResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditGapEntry extends EditRecord
{
    protected static string $resource = GapEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
