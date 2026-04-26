<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class ClearReportCache extends Command
{
    protected $signature = 'cache:clear-reports {--all : Clear all report caches}';

    protected $description = 'Clear cached report data';

    public function handle(): int
    {
        $cleared = 0;

        if ($this->option('all')) {
            foreach ($this->getCacheKeys() as $key) {
                if (Cache::forget($key)) {
                    $cleared++;
                }
            }
            $this->info("Cleared {$cleared} cache entries.");
        } else {
            foreach ($this->getCacheKeys() as $key) {
                Cache::forget($key);
            }
            $this->info('Cleared all report caches.');
        }

        return self::SUCCESS;
    }

    private function getCacheKeys(): array
    {
        return [
            'report:dashboard:summary',
            'report:dashboard:tool_progress',
            'report:dashboard:district_progress',
            'report:dashboard:recent_completions',
            'report:dashboard:active_journeys',
            'report:dashboard:gap_summary',
            'report:journey_status',
            'report:gap_overview',
            'report:low_score_watchlist',
            'report:needs_attention',
            'report:evaluator_activity',
        ];
    }
}
