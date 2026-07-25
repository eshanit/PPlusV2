<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Services\CouchDbUserPushService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Throwable;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        try {
            app(CouchDbUserPushService::class)->push($this->record);
        } catch (Throwable $e) {
            Notification::make()
                ->title('User saved, but CouchDB sync failed')
                ->body($e->getMessage())
                ->warning()
                ->send();
        }
    }
}
