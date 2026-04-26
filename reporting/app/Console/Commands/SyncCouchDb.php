<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class SyncCouchDb extends Command
{
    protected $signature = 'sync:couchdb
                            {--db= : Only sync a specific logical database (e.g. sessions, gaps)}
                            {--reset : Restart sync from the beginning (ignores saved checkpoint)}
                            {--batch=200 : Number of changes to process per batch}';

    protected $description = 'Pull changes from CouchDB and upsert into MySQL';

    // Dimensions must be synced before facts — sessions/gaps have FKs to users and districts.
    private const PROCESSORS = [
        'districts' => 'processDistrict',
        'facilities' => 'processFacility',
        'users' => 'processUser',
        'sessions' => 'processSession',
        'gaps' => 'processGap',
    ];

    public function handle(): int
    {
        try {
            $databases = $this->configuredDatabases();
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $targetDb = $this->option('db');
        $targetDb = is_string($targetDb) && $targetDb !== '' ? $targetDb : null;

        if ($targetDb !== null) {
            $databases = $this->filterTargetDatabase($databases, $targetDb);
        }

        if (empty($databases)) {
            $available = implode(', ', array_keys($this->configuredDatabases()));
            $this->error("Unknown database [{$targetDb}]. Available logical names: {$available}");

            return self::FAILURE;
        }

        $batchSize = max(1, (int) $this->option('batch'));

        foreach ($databases as $logicalName => $database) {
            $this->syncDatabase(
                $logicalName,
                $database['name'],
                $database['processor'],
                $batchSize,
            );
        }

        $this->clearReportCache();

        return self::SUCCESS;
    }

    /**
     * @return array<string, array{name: string, processor: string}>
     */
    private function configuredDatabases(): array
    {
        $configuredNames = config('couchdb.databases', []);
        $databases = [];

        foreach (self::PROCESSORS as $logicalName => $processor) {
            $dbName = $configuredNames[$logicalName] ?? null;

            if (! is_string($dbName) || $dbName === '') {
                throw new RuntimeException("Missing CouchDB database config for [{$logicalName}].");
            }

            $databases[$logicalName] = [
                'name' => $dbName,
                'processor' => $processor,
            ];
        }

        return $databases;
    }

    /**
     * @param  array<string, array{name: string, processor: string}>  $databases
     * @return array<string, array{name: string, processor: string}>
     */
    private function filterTargetDatabase(array $databases, string $targetDb): array
    {
        foreach ($databases as $logicalName => $database) {
            if ($targetDb === $logicalName || $targetDb === $database['name']) {
                return [$logicalName => $database];
            }
        }

        return [];
    }

    private function syncDatabase(string $logicalName, string $dbName, string $processor, int $batchSize): void
    {
        $since = $this->option('reset') ? '0' : $this->getCheckpoint($dbName);

        $this->info("Syncing [{$logicalName} => {$dbName}] since seq: {$since}");
        $processed = 0;
        $errors = 0;

        do {
            $response = $this->fetchChanges($dbName, $since, $batchSize);

            if (! $response) {
                $this->error("  Failed to fetch changes for [{$dbName}]");
                break;
            }

            $results = $response['results'] ?? [];

            foreach ($results as $change) {
                if ($change['deleted'] ?? false) {
                    $this->handleDeleted($logicalName, $dbName, $change['id']);
                    $processed++;

                    continue;
                }

                $doc = $change['doc'] ?? null;

                if (! $doc) {
                    continue;
                }

                try {
                    DB::transaction(fn (): mixed => $this->{$processor}($doc));
                    $processed++;
                } catch (Throwable $e) {
                    $errors++;
                    Log::error("sync:couchdb [{$dbName}] doc {$doc['_id']}: {$e->getMessage()}");
                    $this->warn("  Error on doc {$doc['_id']}: {$e->getMessage()}");
                }
            }

            $since = $response['last_seq'];
            $this->saveCheckpoint($dbName, $since);

            $pending = $response['pending'] ?? 0;
            $this->line("  Processed {$processed} | Pending {$pending} | Errors {$errors}");
        } while (($response['pending'] ?? 0) > 0);

        $this->info("  Done. Total processed: {$processed}, errors: {$errors}");
    }

    private function fetchChanges(string $dbName, string $since, int $limit): ?array
    {
        $url = rtrim((string) config('couchdb.url'), '/')."/{$dbName}/_changes";

        $response = Http::withBasicAuth(
            (string) config('couchdb.user'),
            (string) config('couchdb.password'),
        )
            ->timeout(30)
            ->get($url, [
                'feed' => 'normal',
                'include_docs' => 'true',
                'since' => $since,
                'limit' => $limit,
            ]);

        if ($response->failed()) {
            Log::error('CouchDB changes request failed', [
                'db' => $dbName,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        }

        return $response->json();
    }

    private function processSession(array $doc): void
    {
        $toolId = DB::table('tools')->where('slug', $doc['toolSlug'])->value('id');

        if (! $toolId) {
            throw new RuntimeException("Unknown toolSlug: {$doc['toolSlug']}");
        }

        // Sessions store facility/district as name strings inherited from the mentee profile.
        // Resolve them to IDs so joins work in reports.
        $districtId = $this->resolveDistrictId($doc['districtId'] ?? null);
        $facilityId = $this->resolveFacilityId($districtId, $doc['facilityId'] ?? null);

        DB::table('evaluation_sessions')->upsert([
            'id' => $doc['_id'],
            'evaluation_group_id' => $doc['evaluationGroupId'],
            'mentee_id' => $doc['mentee']['id'],
            'evaluator_id' => $doc['evaluator']['id'],
            'tool_id' => $toolId,
            'eval_date' => date('Y-m-d', intdiv((int) $doc['evalDate'], 1000)),
            'facility_id' => $facilityId,
            'district_id' => $districtId,
            'phase' => $doc['phase'] ?? null,
            'notes' => $doc['notes'] ?? null,
            'couchdb_rev' => $doc['_rev'] ?? null,
            'created_at' => $this->msToDatetime($doc['createdAt'] ?? null),
            'updated_at' => $this->msToDatetime($doc['updatedAt'] ?? null),
            'synced_at' => now(),
        ], uniqueBy: ['id'], update: [
            'evaluation_group_id',
            'eval_date',
            'facility_id',
            'district_id',
            'phase',
            'notes',
            'couchdb_rev',
            'updated_at',
            'synced_at',
        ]);

        DB::table('session_item_scores')->where('session_id', $doc['_id'])->delete();

        $scores = [
            ...$this->sessionScoreRows($doc['_id'], $doc['itemScores'] ?? []),
            ...$this->sessionScoreRows($doc['_id'], $doc['counsellingScores'] ?? []),
        ];

        if ($scores !== []) {
            DB::table('session_item_scores')->insert($scores);
        }
    }

    private function processGap(array $doc): void
    {
        $toolId = DB::table('tools')->where('slug', $doc['toolSlug'])->value('id');

        if (! $toolId) {
            throw new RuntimeException("Unknown toolSlug: {$doc['toolSlug']}");
        }

        DB::table('gap_entries')->upsert([
            'id' => $doc['_id'],
            'evaluation_group_id' => $doc['evaluationGroupId'],
            'mentee_id' => $doc['menteeId'],
            'evaluator_id' => $doc['evaluatorId'],
            'tool_id' => $toolId,
            'identified_at' => date('Y-m-d', intdiv((int) $doc['identifiedAt'], 1000)),
            'description' => $doc['description'],
            'domains' => json_encode($doc['domains'] ?? []),
            'covered_in_mentorship' => $doc['coveredInMentorship'] ?? null,
            'covering_later' => $doc['coveringLater'] ?? false,
            'timeline' => $doc['timeline'] ?? null,
            'supervision_level' => $doc['supervisionLevel'] ?? null,
            'resolution_note' => $doc['resolutionNote'] ?? null,
            'resolved_at' => isset($doc['resolvedAt'])
                ? date('Y-m-d', intdiv((int) $doc['resolvedAt'], 1000))
                : null,
            'couchdb_rev' => $doc['_rev'] ?? null,
            'created_at' => $this->msToDatetime($doc['createdAt'] ?? null),
            'updated_at' => $this->msToDatetime($doc['updatedAt'] ?? null),
            'synced_at' => now(),
        ], uniqueBy: ['id'], update: [
            'description',
            'domains',
            'covered_in_mentorship',
            'covering_later',
            'timeline',
            'supervision_level',
            'resolution_note',
            'resolved_at',
            'couchdb_rev',
            'updated_at',
            'synced_at',
        ]);
    }

    private function processUser(array $doc): void
    {
        // User docs use 'facility' and 'district' (name strings), not 'facilityId'/'districtId'.
        // Resolve names to IDs so FK constraints are satisfied.
        $districtId = $this->resolveDistrictId($doc['district'] ?? null);
        $facilityId = $this->resolveFacilityId($districtId, $doc['facility'] ?? null);

        DB::table('users')->upsert([
            'id' => $doc['_id'],
            'firstname' => $doc['firstname'],
            'lastname' => $doc['lastname'],
            'username' => $doc['username'] ?? null,
            'profession' => $doc['profession'] ?? null,
            'facility_id' => $facilityId,
            'district_id' => $districtId,
            'couchdb_rev' => $doc['_rev'] ?? null,
            'created_at' => now(),
            'updated_at' => $this->msToDatetime($doc['updatedAt'] ?? null) ?? now(),
            'synced_at' => now(),
        ], uniqueBy: ['id'], update: [
            'firstname',
            'lastname',
            'username',
            'profession',
            'facility_id',
            'district_id',
            'couchdb_rev',
            'updated_at',
            'synced_at',
        ]);
    }

    private function processDistrict(array $doc): void
    {
        // District docs use 'district' for the name field and embed facilities as a string array.
        // Facilities have no separate CouchDB database — they live inside district docs.
        $districtId = $doc['_id'];
        $districtName = $doc['district'] ?? null;

        if (! $districtName) {
            throw new RuntimeException("District doc {$districtId} missing 'district' field");
        }

        DB::table('districts')->upsert([
            'id' => $districtId,
            'name' => $districtName,
            'couchdb_rev' => $doc['_rev'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
            'synced_at' => now(),
        ], uniqueBy: ['id'], update: ['name', 'couchdb_rev', 'updated_at', 'synced_at']);

        foreach ($doc['facilities'] ?? [] as $facilityName) {
            if (! is_string($facilityName) || $facilityName === '') {
                continue;
            }

            // Use a deterministic ID so re-syncing the same district doesn't create duplicates.
            DB::table('facilities')->upsert([
                'id' => md5($districtId.'::'.$facilityName),
                'district_id' => $districtId,
                'name' => $facilityName,
                'couchdb_rev' => null,
                'created_at' => now(),
                'updated_at' => now(),
                'synced_at' => now(),
            ], uniqueBy: ['id'], update: ['name', 'updated_at', 'synced_at']);
        }
    }

    private function processFacility(array $doc): void
    {
        // Facilities are embedded in district docs and extracted by processDistrict.
        // The penplus_facilities CouchDB database is not used; this processor is a no-op.
    }

    /**
     * @param  array<int, array{itemSlug: string, menteeScore: int|null, notes?: string}>  $scores
     * @return array<int, array{session_id: string, item_id: int, mentee_score: int|null, notes: string|null}>
     */
    private function sessionScoreRows(string $sessionId, array $scores): array
    {
        $rows = [];

        foreach ($scores as $score) {
            $itemId = DB::table('evaluation_items')->where('slug', $score['itemSlug'])->value('id');

            if (! $itemId) {
                continue;
            }

            $rows[] = [
                'session_id' => $sessionId,
                'item_id' => (int) $itemId,
                'mentee_score' => $score['menteeScore'],
                'notes' => $score['notes'] ?? null,
            ];
        }

        return $rows;
    }

    private function resolveDistrictId(?string $districtName): ?string
    {
        if (! $districtName) {
            return null;
        }

        return DB::table('districts')->where('name', $districtName)->value('id');
    }

    private function resolveFacilityId(?string $districtId, ?string $facilityName): ?string
    {
        if (! $districtId || ! $facilityName) {
            return null;
        }

        return DB::table('facilities')
            ->where('district_id', $districtId)
            ->where('name', $facilityName)
            ->value('id');
    }

    private function handleDeleted(string $logicalName, string $dbName, string $docId): void
    {
        Log::info("sync:couchdb [{$logicalName} => {$dbName}] deleted doc: {$docId}");
    }

    private function getCheckpoint(string $dbName): string
    {
        return DB::table('sync_checkpoints')
            ->where('db_name', $dbName)
            ->value('last_seq') ?? '0';
    }

    private function saveCheckpoint(string $dbName, string $seq): void
    {
        DB::table('sync_checkpoints')->upsert([
            'db_name' => $dbName,
            'last_seq' => $seq,
            'last_synced_at' => now(),
        ], uniqueBy: ['db_name'], update: ['last_seq', 'last_synced_at']);
    }

    private function msToDatetime(int|string|null $ms): ?string
    {
        if (! is_numeric($ms)) {
            return null;
        }

        return date('Y-m-d H:i:s', intdiv((int) $ms, 1000));
    }

    private function clearReportCache(): void
    {
        $userIds = DB::table('users')->pluck('id')->toArray();
        $districtIds = array_merge([null], DB::table('districts')->pluck('id')->toArray());

        $keys = [
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

        foreach ($keys as $key) {
            Cache::forget($key);

            foreach ($userIds as $userId) {
                foreach ($districtIds as $districtId) {
                    Cache::forget("{$key}:user{$userId}:district{$districtId}");
                }
            }
        }

        $this->info('Cleared report caches.');
    }
}
