<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Pushes an admin-created/edited User (MySQL) up to the penplus_users CouchDB
 * database, so monitoring devices pick it up via their normal PouchDB sync.
 *
 * This is the reverse direction of SyncCouchDb::processUser(), which pulls
 * penplus_users docs back down into MySQL. Doc shape must match IStoredUser
 * in monitoring/app/stores/userStore.ts.
 */
class CouchDbUserPushService
{
    public function push(User $user): void
    {
        $user->loadMissing(['district', 'facility']);

        $doc = [
            'type' => 'user',
            'firstname' => $user->firstname,
            'lastname' => $user->lastname,
            'username' => $user->username ?? $user->id,
            'profession' => $user->profession,
            'facility' => $user->facility?->name,
            'district' => $user->district?->name,
            'updatedAt' => (int) round($user->updated_at?->valueOf() ?? now()->valueOf()),
            'createdAt' => (int) round($user->created_at?->valueOf() ?? now()->valueOf()),
        ];

        if ($user->couchdb_rev) {
            $doc['_rev'] = $user->couchdb_rev;
        }

        $response = $this->put($user->id, $doc);

        if ($response->status() === 409) {
            // Someone else moved the doc forward (e.g. edited on a device) — fetch the
            // current rev and retry once rather than clobbering their revision history.
            $current = Http::withBasicAuth($this->couchUser(), $this->couchPassword())
                ->get("{$this->baseUrl()}/{$this->dbName()}/{$user->id}");

            if ($current->successful()) {
                $doc['_rev'] = $current->json('_rev');
                $response = $this->put($user->id, $doc);
            }
        }

        if ($response->failed()) {
            throw new RuntimeException(
                "Failed to push user [{$user->id}] to CouchDB: {$response->status()} {$response->body()}"
            );
        }

        $user->forceFill(['couchdb_rev' => $response->json('rev')])->saveQuietly();
    }

    private function put(string $id, array $doc): Response
    {
        return Http::withBasicAuth($this->couchUser(), $this->couchPassword())
            ->put("{$this->baseUrl()}/{$this->dbName()}/{$id}", $doc);
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('couchdb.url'), '/');
    }

    private function dbName(): string
    {
        return (string) config('couchdb.databases.users');
    }

    private function couchUser(): string
    {
        return (string) config('couchdb.user');
    }

    private function couchPassword(): string
    {
        return (string) config('couchdb.password');
    }
}
