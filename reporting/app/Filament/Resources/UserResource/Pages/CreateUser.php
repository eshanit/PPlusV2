<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Services\CouchDbUserPushService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Throwable;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function afterCreate(): void
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
