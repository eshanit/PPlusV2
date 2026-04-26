<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements FilamentUser, HasName
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
        'role_id',
        'district_id',
        'facility_id',
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

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
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

    public function getFilamentName(): string
    {
        return "{$this->firstname} {$this->lastname}";
    }

    public function getFullNameAttribute(): string
    {
        return $this->getFilamentName();
    }

    public function isAdmin(): bool
    {
        return $this->role?->name === 'admin';
    }

    public function isDistrictAdmin(): bool
    {
        return $this->role?->name === 'district_admin';
    }
}
