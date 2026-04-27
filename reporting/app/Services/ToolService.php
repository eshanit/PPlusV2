<?php

namespace App\Services;

use Illuminate\Support\Auth;
use Illuminate\Support\Facades\DB;

class ToolService
{
    public function getAllForDropdown(): array
    {
        return DB::table('tools')
            ->where('slug', '!=', 'counselling')
            ->orderBy('sort_order')
            ->get(['id', 'label'])
            ->map(fn ($t) => ['id' => (int) $t->id, 'label' => $t->label])
            ->all();
    }

    public function getDistrictsForUser(): array
    {
        $user = Auth::user();

        if ($user && ! $user->isAdmin() && $user->district_id) {
            return DB::table('districts')
                ->where('id', $user->district_id)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn ($d) => ['id' => (int) $d->id, 'name' => $d->name])
                ->all();
        }

        return DB::table('districts')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($d) => ['id' => (int) $d->id, 'name' => $d->name])
            ->all();
    }

    public function getById(int $id): ?object
    {
        return DB::table('tools')
            ->where('id', $id)
            ->first();
    }
}
