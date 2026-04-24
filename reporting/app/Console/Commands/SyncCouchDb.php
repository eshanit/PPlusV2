<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
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

    private const PROCESSORS = [
        'sessions' => 'processSession',
        'gaps' => 'processGap',
        'users' => 'processUser',
        'districts' => 'processDistrict',
        'facilities' => 'processFacility',
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

        DB::table('evaluation_sessions')->upsert([
            'id' => $doc['_id'],
            'evaluation_group_id' => $doc['evaluationGroupId'],
            'mentee_id' => $doc['mentee']['id'],
            'evaluator_id' => $doc['evaluator']['id'],
            'tool_id' => $toolId,
            'eval_date' => date('Y-m-d', intdiv((int) $doc['evalDate'], 1000)),
            'facility_id' => $doc['facilityId'] ?? null,
            'district_id' => $doc['districtId'] ?? null,
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
        DB::table('users')->upsert([
            'id' => $doc['_id'],
            'firstname' => $doc['firstname'],
            'lastname' => $doc['lastname'],
            'username' => $doc['username'] ?? null,
            'profession' => $doc['profession'] ?? null,
            'facility_id' => $doc['facilityId'] ?? null,
            'district_id' => $doc['districtId'] ?? null,
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
        DB::table('districts')->upsert([
            'id' => $doc['_id'],
            'name' => $doc['name'],
            'couchdb_rev' => $doc['_rev'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
            'synced_at' => now(),
        ], uniqueBy: ['id'], update: ['name', 'couchdb_rev', 'updated_at', 'synced_at']);
    }

    private function processFacility(array $doc): void
    {
        DB::table('facilities')->upsert([
            'id' => $doc['_id'],
            'district_id' => $doc['districtId'],
            'name' => $doc['name'],
            'couchdb_rev' => $doc['_rev'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
            'synced_at' => now(),
        ], uniqueBy: ['id'], update: ['district_id', 'name', 'couchdb_rev', 'updated_at', 'synced_at']);
    }

    /**
     * @param  array<int, array{itemSlug: string, menteeScore: int|null}>  $scores
     * @return array<int, array{session_id: string, item_id: int, mentee_score: int|null}>
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
            ];
        }

        return $rows;
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
}
