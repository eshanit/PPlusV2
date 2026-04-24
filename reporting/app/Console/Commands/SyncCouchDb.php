<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SyncCouchDb extends Command
{
    protected $signature = 'sync:couchdb
                            {--db= : Only sync a specific database (e.g. sessions, gaps)}
                            {--reset : Restart sync from the beginning (ignores saved checkpoint)}
                            {--batch=200 : Number of changes to process per batch}';

    protected $description = 'Pull changes from CouchDB and upsert into MySQL';

    // Map each CouchDB database name to its processor method
    private array $databases = [
        'penplus_sessions'  => 'processSession',
        'penplus_gaps'      => 'processGap',
        'penplus_users'     => 'processUser',
        'penplus_districts' => 'processDistrict',
        'penplus_facilities'=> 'processFacility',
    ];

    public function handle(): int
    {
        $targetDb  = $this->option('db');
        $batchSize = (int) $this->option('batch');
        $databases = $targetDb
            ? array_intersect_key($this->databases, [$targetDb => true])
            : $this->databases;

        if (empty($databases)) {
            $this->error("Unknown database: {$targetDb}");
            return self::FAILURE;
        }

        foreach ($databases as $dbName => $processor) {
            $this->syncDatabase($dbName, $processor, $batchSize);
        }

        return self::SUCCESS;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Core sync loop
    // ─────────────────────────────────────────────────────────────────────────

    private function syncDatabase(string $dbName, string $processor, int $batchSize): void
    {
        $since = $this->option('reset') ? '0' : $this->getCheckpoint($dbName);

        $this->info("Syncing [{$dbName}] since seq: {$since}");
        $processed = 0;
        $errors    = 0;

        do {
            $response = $this->fetchChanges($dbName, $since, $batchSize);

            if (! $response) {
                $this->error("  Failed to fetch changes for [{$dbName}]");
                break;
            }

            $results = $response['results'] ?? [];

            foreach ($results as $change) {
                if ($change['deleted'] ?? false) {
                    $this->handleDeleted($dbName, $change['id']);
                    $processed++;
                    continue;
                }

                $doc = $change['doc'] ?? null;
                if (! $doc) {
                    continue;
                }

                try {
                    DB::transaction(fn () => $this->{$processor}($doc));
                    $processed++;
                } catch (\Throwable $e) {
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

    // ─────────────────────────────────────────────────────────────────────────
    // CouchDB HTTP
    // ─────────────────────────────────────────────────────────────────────────

    private function fetchChanges(string $dbName, string $since, int $limit): ?array
    {
        $url = rtrim(config('couchdb.url'), '/') . "/{$dbName}/_changes";

        $response = Http::withBasicAuth(config('couchdb.user'), config('couchdb.password'))
            ->timeout(30)
            ->get($url, [
                'feed'         => 'normal',
                'include_docs' => 'true',
                'since'        => $since,
                'limit'        => $limit,
            ]);

        if ($response->failed()) {
            Log::error("CouchDB changes request failed", [
                'db'     => $dbName,
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            return null;
        }

        return $response->json();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Document processors — one method per CouchDB database
    // ─────────────────────────────────────────────────────────────────────────

    private function processSession(array $doc): void
    {
        // Resolve foreign key IDs
        $toolId = DB::table('tools')->where('slug', $doc['toolSlug'])->value('id');

        if (! $toolId) {
            throw new \RuntimeException("Unknown toolSlug: {$doc['toolSlug']}");
        }

        // Upsert the session row
        DB::table('evaluation_sessions')->upsert([
            'id'                  => $doc['_id'],
            'evaluation_group_id' => $doc['evaluationGroupId'],
            'mentee_id'           => $doc['mentee']['id'],
            'evaluator_id'        => $doc['evaluator']['id'],
            'tool_id'             => $toolId,
            'eval_date'           => date('Y-m-d', intdiv($doc['evalDate'], 1000)),
            'facility_id'         => $doc['facilityId'] ?? null,
            'district_id'         => $doc['districtId'] ?? null,
            'phase'               => $doc['phase'] ?? null,
            'notes'               => $doc['notes'] ?? null,
            'couchdb_rev'         => $doc['_rev'],
            'created_at'          => $this->msToDatetime($doc['createdAt']),
            'updated_at'          => $this->msToDatetime($doc['updatedAt']),
            'synced_at'           => now(),
        ], uniqueBy: ['id'], update: [
            'evaluation_group_id', 'eval_date', 'facility_id', 'district_id',
            'phase', 'notes', 'couchdb_rev', 'updated_at', 'synced_at',
        ]);

        // Re-sync all item scores for this session (delete + re-insert is safe
        // because session_item_scores has no downstream FK references)
        DB::table('session_item_scores')->where('session_id', $doc['_id'])->delete();

        $scores = [];

        foreach (($doc['itemScores'] ?? []) as $score) {
            $itemId = DB::table('evaluation_items')->where('slug', $score['itemSlug'])->value('id');
            if (! $itemId) {
                continue;
            }
            $scores[] = [
                'session_id'   => $doc['_id'],
                'item_id'      => $itemId,
                'mentee_score' => $score['menteeScore'],
            ];
        }

        foreach (($doc['counsellingScores'] ?? []) as $score) {
            $itemId = DB::table('evaluation_items')->where('slug', $score['itemSlug'])->value('id');
            if (! $itemId) {
                continue;
            }
            $scores[] = [
                'session_id'   => $doc['_id'],
                'item_id'      => $itemId,
                'mentee_score' => $score['menteeScore'],
            ];
        }

        if (! empty($scores)) {
            DB::table('session_item_scores')->insert($scores);
        }
    }

    private function processGap(array $doc): void
    {
        $toolId = DB::table('tools')->where('slug', $doc['toolSlug'])->value('id');

        DB::table('gap_entries')->upsert([
            'id'                    => $doc['_id'],
            'evaluation_group_id'   => $doc['evaluationGroupId'],
            'mentee_id'             => $doc['menteeId'],
            'evaluator_id'          => $doc['evaluatorId'],
            'tool_id'               => $toolId,
            'identified_at'         => date('Y-m-d', intdiv($doc['identifiedAt'], 1000)),
            'description'           => $doc['description'],
            'domains'               => json_encode($doc['domains'] ?? []),
            'covered_in_mentorship' => $doc['coveredInMentorship'] ?? null,
            'covering_later'        => $doc['coveringLater'] ?? false,
            'timeline'              => $doc['timeline'] ?? null,
            'supervision_level'     => $doc['supervisionLevel'] ?? null,
            'resolution_note'       => $doc['resolutionNote'] ?? null,
            'resolved_at'           => isset($doc['resolvedAt'])
                ? date('Y-m-d', intdiv($doc['resolvedAt'], 1000))
                : null,
            'couchdb_rev'           => $doc['_rev'],
            'created_at'            => $this->msToDatetime($doc['createdAt']),
            'updated_at'            => $this->msToDatetime($doc['updatedAt']),
            'synced_at'             => now(),
        ], uniqueBy: ['id'], update: [
            'description', 'domains', 'covered_in_mentorship', 'covering_later',
            'timeline', 'supervision_level', 'resolution_note', 'resolved_at',
            'couchdb_rev', 'updated_at', 'synced_at',
        ]);
    }

    private function processUser(array $doc): void
    {
        DB::table('users')->upsert([
            'id'          => $doc['_id'],
            'firstname'   => $doc['firstname'],
            'lastname'    => $doc['lastname'],
            'username'    => $doc['username'] ?? null,
            'profession'  => $doc['profession'] ?? null,
            'facility_id' => $doc['facilityId'] ?? null,
            'district_id' => $doc['districtId'] ?? null,
            'couchdb_rev' => $doc['_rev'],
            'created_at'  => now(),
            'updated_at'  => $this->msToDatetime($doc['updatedAt'] ?? null),
            'synced_at'   => now(),
        ], uniqueBy: ['id'], update: [
            'firstname', 'lastname', 'username', 'profession',
            'facility_id', 'district_id', 'couchdb_rev', 'updated_at', 'synced_at',
        ]);
    }

    private function processDistrict(array $doc): void
    {
        DB::table('districts')->upsert([
            'id'          => $doc['_id'],
            'name'        => $doc['name'],
            'couchdb_rev' => $doc['_rev'],
            'created_at'  => now(),
            'updated_at'  => now(),
            'synced_at'   => now(),
        ], uniqueBy: ['id'], update: ['name', 'couchdb_rev', 'updated_at', 'synced_at']);
    }

    private function processFacility(array $doc): void
    {
        DB::table('facilities')->upsert([
            'id'          => $doc['_id'],
            'district_id' => $doc['districtId'],
            'name'        => $doc['name'],
            'couchdb_rev' => $doc['_rev'],
            'created_at'  => now(),
            'updated_at'  => now(),
            'synced_at'   => now(),
        ], uniqueBy: ['id'], update: ['district_id', 'name', 'couchdb_rev', 'updated_at', 'synced_at']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Soft-delete — mark as deleted rather than hard-removing
    // ─────────────────────────────────────────────────────────────────────────

    private function handleDeleted(string $dbName, string $docId): void
    {
        // Currently a no-op — extend when soft-delete columns are added.
        Log::info("sync:couchdb [{$dbName}] deleted doc: {$docId}");
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Checkpoint helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function getCheckpoint(string $dbName): string
    {
        return DB::table('sync_checkpoints')
            ->where('db_name', $dbName)
            ->value('last_seq') ?? '0';
    }

    private function saveCheckpoint(string $dbName, string $seq): void
    {
        DB::table('sync_checkpoints')->upsert([
            'db_name'        => $dbName,
            'last_seq'       => $seq,
            'last_synced_at' => now(),
        ], uniqueBy: ['db_name'], update: ['last_seq', 'last_synced_at']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function msToDatetime(?int $ms): ?string
    {
        return $ms ? date('Y-m-d H:i:s', intdiv($ms, 1000)) : null;
    }
}
