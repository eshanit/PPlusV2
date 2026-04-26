<?php

namespace App\Scopes;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

trait ScopesReportsByUser
{
    public static function scopeForUser(Builder $query, ?User $user = null): Builder
    {
        $user = $user ?? Auth::user();

        if (! $user) {
            return $query;
        }

        if ($user->isAdmin()) {
            return $query;
        }

        if ($user->isDistrictAdmin() && $user->district_id) {
            return $query->where('district_id', $user->district_id);
        }

        return $query->where('district_id', $user->district_id);
    }
}
