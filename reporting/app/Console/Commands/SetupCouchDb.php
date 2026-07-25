<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class SetupCouchDb extends Command
{
    protected $signature = 'couchdb:setup';

    protected $description = 'Create the CouchDB databases required by the monitoring/reporting sync, if they don\'t already exist';

    public function handle(): int
    {
        $url = rtrim((string) config('couchdb.url'), '/');
        $user = (string) config('couchdb.user');
        $password = (string) config('couchdb.password');
        $databases = config('couchdb.databases', []);

        if ($databases === []) {
            $this->error('No databases configured in config/couchdb.php.');

            return self::FAILURE;
        }

        $failed = false;

        foreach ($databases as $logicalName => $dbName) {
            $response = Http::withBasicAuth($user, $password)->put("{$url}/{$dbName}");

            if ($response->successful()) {
                $this->info("Created [{$logicalName} => {$dbName}]");
            } elseif ($response->status() === 412) {
                $this->line("Already exists [{$logicalName} => {$dbName}]");
            } else {
                $failed = true;
                $this->error("Failed [{$logicalName} => {$dbName}]: {$response->status()} {$response->body()}");
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
