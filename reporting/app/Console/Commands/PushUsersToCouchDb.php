<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\CouchDbUserPushService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Pushes users from MySQL to the CouchDB users database, including their role.
 *
 * Users saved in the dashboard are pushed automatically. Run this once after
 * deploying the evaluator-role change, or after assigning roles in bulk, so
 * monitoring devices receive the roles of existing users.
 */
class PushUsersToCouchDb extends Command
{
    protected $signature = 'couchdb:push-users
        {--all : Push every user, not only users with a role}';

    protected $description = 'Push users and their roles from MySQL to CouchDB for monitoring devices';

    public function handle(CouchDbUserPushService $pusher): int
    {
        $query = User::query()->with(['role', 'district', 'facility']);

        if (! $this->option('all')) {
            $query->whereNotNull('role_id');
        }

        $pushed = 0;
        $failed = 0;

        $query->orderBy('lastname')->each(function (User $user) use ($pusher, &$pushed, &$failed) {
            try {
                $pusher->push($user);
                $pushed++;
                $this->line("Pushed {$user->firstname} {$user->lastname} (".($user->role?->name ?? 'no role').')');
            } catch (Throwable $e) {
                $failed++;
                $this->error("Failed {$user->firstname} {$user->lastname}: {$e->getMessage()}");
            }
        });

        $this->info("Done. Pushed {$pushed}, failed {$failed}.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
