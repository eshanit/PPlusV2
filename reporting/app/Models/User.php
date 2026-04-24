<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements FilamentUser
{
    use HasFactory, Notifiable;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'firstname',
        'lastname',
        'username',
        'profession',
        'facility_id',
        'district_id',
        'couchdb_rev',
        'synced_at',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'synced_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->password !== null;
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    public function sessionsAsMentee(): HasMany
    {
        return $this->hasMany(EvaluationSession::class, 'mentee_id');
    }

    public function sessionsAsEvaluator(): HasMany
    {
        return $this->hasMany(EvaluationSession::class, 'evaluator_id');
    }

    public function gapsAsMentee(): HasMany
    {
        return $this->hasMany(GapEntry::class, 'mentee_id');
    }

    public function getFullNameAttribute(): string
    {
        return "{$this->firstname} {$this->lastname}";
    }
}
