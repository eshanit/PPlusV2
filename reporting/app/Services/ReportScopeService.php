<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ReportScopeService
{
    /**
     * Returns a [sql, bindings] pair for use with whereRaw() that scopes a query to
     * the current user's district. Admins and users without a district_id see all rows.
     *
     * Usage: ->whereRaw(...$this->scope->scope('v_journey_summary'))
     *
     * district_id is a char(36) UUID, not an integer — casting it to (int) makes the
     * bound value 0, and MySQL's implicit numeric coercion then matches that against
     * every UUID string (since a non-numeric string casts to 0 too), silently turning
     * this into a no-op for every non-admin user. Bind the raw string instead.
     *
     * @return array{0: string, 1: array<int, string>}
     */
    public function scope(string $table, string $column = 'district_id'): array
    {
        $user = Auth::user();

        if (! $user || $user->isAdmin() || ! $user->district_id) {
            return ['1=1', []];
        }

        return ["{$table}.{$column} = ?", [$user->district_id]];
    }

    /**
     * Returns a [sql, bindings] pair that scopes gap_entries to the current user's
     * district via a subquery on evaluation_sessions.
     *
     * Usage: ->whereRaw(...$this->scope->gapScope())
     *
     * @return array{0: string, 1: array<int, string>}
     */
    public function gapScope(): array
    {
        $user = Auth::user();

        if (! $user || $user->isAdmin() || ! $user->district_id) {
            return ['1=1', []];
        }

        return [
            'gap_entries.evaluation_group_id IN (SELECT evaluation_group_id FROM evaluation_sessions WHERE district_id = ?)',
            [$user->district_id],
        ];
    }

    /**
     * Returns mentees with journeys visible to the current user for report filters.
     *
     * @return array<int, array{id: string, name: string}>
     */
    public function menteeOptions(): array
    {
        return DB::table('v_journey_summary')
            ->whereRaw(...$this->scope('v_journey_summary'))
            ->select('mentee_id', 'mentee_firstname', 'mentee_lastname')
            ->distinct()
            ->orderBy('mentee_firstname')
            ->orderBy('mentee_lastname')
            ->get()
            ->map(fn (object $mentee): array => [
                'id' => (string) $mentee->mentee_id,
                'name' => trim("{$mentee->mentee_firstname} {$mentee->mentee_lastname}"),
            ])
            ->all();
    }

    /**
     * Returns the district_id of the current user, or null for admins / unscoped users.
     */
    public function getUserDistrictId(): ?int
    {
        $user = Auth::user();

        return $user?->district_id;
    }

    public function applyDistrictScope(object $query, ?User $user = null): object
    {
        $user = $user ?? Auth::user();

        if (! $user || $user->isAdmin()) {
            return $query;
        }

        $districtId = $user->district_id;

        if (! $districtId) {
            return $query;
        }

        if ($query instanceof Builder) {
            return $query->where('district_id', $districtId);
        }

        return (object) ['query' => $query, 'districtId' => $districtId];
    }
}
