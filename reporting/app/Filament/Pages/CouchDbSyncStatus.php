<?php

namespace App\Filament\Pages;

use App\Models\SyncCheckpoint;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;

class CouchDbSyncStatus extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-arrow-path';

    protected static ?string $navigationLabel = 'CouchDB Sync';

    protected static ?string $navigationGroup = 'Reference Data';

    protected static ?int $navigationSort = 10;

    protected static ?string $slug = 'couchdb-sync';

    protected static ?string $title = 'CouchDB Sync';

    protected static string $view = 'filament.pages.couchdb-sync-status';

    /** @var array<int, array{logical_name: string, db_name: string, last_synced_at: ?string, last_seq: ?string}> */
    public array $checkpoints = [];

    public bool $syncing = false;

    public static function canAccess(): bool
    {
        return Auth::user()?->isAdmin() ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function mount(): void
    {
        $this->loadCheckpoints();
    }

    public function loadCheckpoints(): void
    {
        /** @var array<string, string> $databases */
        $databases = config('couchdb.databases', []);
        $existing = SyncCheckpoint::all()->keyBy('db_name');

        $this->checkpoints = collect($databases)
            ->map(function (string $dbName, string $logicalName) use ($existing): array {
                $checkpoint = $existing->get($dbName);

                return [
                    'logical_name' => $logicalName,
                    'db_name' => $dbName,
                    'last_synced_at' => $checkpoint?->last_synced_at?->diffForHumans(),
                    'last_seq' => $checkpoint?->last_seq,
                ];
            })
            ->values()
            ->all();
    }

    public function syncAll(): void
    {
        $this->runSync();
    }

    public function syncOne(string $logicalName): void
    {
        $this->runSync($logicalName);
    }

    private function runSync(?string $logicalName = null): void
    {
        $this->syncing = true;

        $exitCode = Artisan::call('sync:couchdb', array_filter([
            '--db' => $logicalName,
        ]));

        $output = trim(Artisan::output());
        $this->loadCheckpoints();
        $this->syncing = false;

        $notification = Notification::make()
            ->title($exitCode === 0 ? 'Sync complete' : 'Sync finished with errors')
            ->body($output !== '' ? $output : 'No output.');

        $exitCode === 0 ? $notification->success() : $notification->warning();

        $notification->send();
    }
}
