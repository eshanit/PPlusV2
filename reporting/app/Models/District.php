<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class District extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'name',
        'couchdb_rev',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'synced_at' => 'datetime',
        ];
    }

    public function facilities(): HasMany
    {
        return $this->hasMany(Facility::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function evaluationSessions(): HasMany
    {
        return $this->hasMany(EvaluationSession::class);
    }
}
