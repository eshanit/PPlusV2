<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class ReportScopeService
{
    /**
     * Returns a [sql, bindings] pair for use with whereRaw() that scopes a query to
     * the current user's district. Admins and users without a district_id see all rows.
     *
     * Usage: ->whereRaw(...$this->scope->scope('v_journey_summary'))
     *
     * @return array{0: string, 1: array<int, int>}
     */
    public function scope(string $table, string $column = 'district_id'): array
    {
        $user = Auth::user();

        if (! $user || $user->isAdmin() || ! $user->district_id) {
            return ['1=1', []];
        }

        return ["{$table}.{$column} = ?", [(int) $user->district_id]];
    }

    /**
     * Returns a [sql, bindings] pair that scopes gap_entries to the current user's
     * district via a subquery on evaluation_sessions.
     *
     * Usage: ->whereRaw(...$this->scope->gapScope())
     *
     * @return array{0: string, 1: array<int, int>}
     */
    public function gapScope(): array
    {
        $user = Auth::user();

        if (! $user || $user->isAdmin() || ! $user->district_id) {
            return ['1=1', []];
        }

        return [
            'gap_entries.evaluation_group_id IN (SELECT evaluation_group_id FROM evaluation_sessions WHERE district_id = ?)',
            [(int) $user->district_id],
        ];
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
