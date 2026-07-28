<?php

namespace App\Filament\Widgets;

use App\Models\SyncCheckpoint;
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class SyncHealthOverview extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $checkpoints = SyncCheckpoint::all()->keyBy('db_name');

        $stats = collect(config('couchdb.databases', []))
            ->map(function (string $dbName, string $logicalName) use ($checkpoints): Stat {
                $lastSyncedAt = $checkpoints->get($dbName)?->last_synced_at;

                return $this->syncStat($logicalName, $lastSyncedAt);
            })
            ->values()
            ->all();

        $stats[] = $this->couchDbConnectivityStat();

        return $stats;
    }

    private function syncStat(string $logicalName, ?Carbon $lastSyncedAt): Stat
    {
        $label = ucfirst($logicalName);

        if (! $lastSyncedAt) {
            return Stat::make($label, 'Never synced')
                ->description('No successful sync yet')
                ->descriptionIcon('heroicon-m-x-circle')
                ->color('danger');
        }

        $minutesAgo = $lastSyncedAt->diffInMinutes(now());

        [$description, $icon, $color] = match (true) {
            $minutesAgo <= 10 => ['Healthy', 'heroicon-m-check-circle', 'success'],
            $minutesAgo <= 60 => ['Delayed', 'heroicon-m-exclamation-triangle', 'warning'],
            default => ['Stale', 'heroicon-m-x-circle', 'danger'],
        };

        return Stat::make($label, $lastSyncedAt->diffForHumans())
            ->description($description)
            ->descriptionIcon($icon)
            ->color($color);
    }

    private function couchDbConnectivityStat(): Stat
    {
        $reachable = Cache::remember('admin:couchdb_reachable', 60, function (): bool {
            try {
                $response = Http::withBasicAuth(
                    (string) config('couchdb.user'),
                    (string) config('couchdb.password'),
                )->timeout(3)->get(rtrim((string) config('couchdb.url'), '/').'/_up');

                return $response->successful();
            } catch (\Throwable) {
                return false;
            }
        });

        return $reachable
            ? Stat::make('CouchDB', 'Reachable')
                ->description('Connection healthy')
                ->descriptionIcon('heroicon-m-signal')
                ->color('success')
            : Stat::make('CouchDB', 'Unreachable')
                ->description('Check the CouchDB server')
                ->descriptionIcon('heroicon-m-signal-slash')
                ->color('danger');
    }
}
