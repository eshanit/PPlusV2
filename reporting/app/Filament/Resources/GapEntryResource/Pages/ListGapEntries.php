<?php

namespace App\Filament\Resources\GapEntryResource\Pages;

use App\Filament\Resources\GapEntryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListGapEntries extends ListRecords
{
    protected static string $resource = GapEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
