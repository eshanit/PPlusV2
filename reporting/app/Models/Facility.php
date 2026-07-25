<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Facility extends Model
{
    use SoftDeletes;

    public $incrementing = false;

    protected $keyType = 'string';

    protected static function booted(): void
    {
        static::creating(function (Facility $facility): void {
            $facility->id ??= (string) Str::uuid();
        });
    }

    protected $fillable = [
        'id',
        'district_id',
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

    public function district(): BelongsTo
    {
        // withTrashed: a facility under a since-deleted district should still show its name.
        return $this->belongsTo(District::class)->withTrashed();
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
