<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class ReportScopeService
{
    public function applyDistrictScope(Builder|object $query, ?User $user = null): Builder|object
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

    public function scopeForCurrentUser(string $table, string $column = 'district_id'): string
    {
        $user = Auth::user();

        if (! $user || $user->isAdmin() || ! $user->district_id) {
            return '1=1';
        }

        return "{$table}.{$column} = {$user->district_id}";
    }

    public function getUserDistrictId(): ?int
    {
        $user = Auth::user();

        return $user?->district_id;
    }
}
