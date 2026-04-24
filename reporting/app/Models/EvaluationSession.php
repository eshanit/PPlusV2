<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EvaluationSession extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'evaluation_group_id',
        'mentee_id',
        'evaluator_id',
        'tool_id',
        'eval_date',
        'facility_id',
        'district_id',
        'phase',
        'notes',
        'couchdb_rev',
        'synced_at',
        'created_at',
        'updated_at',
    ];

    protected function casts(): array
    {
        return [
            'eval_date' => 'date',
            'synced_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function mentee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mentee_id');
    }

    public function evaluator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'evaluator_id');
    }

    public function tool(): BelongsTo
    {
        return $this->belongsTo(Tool::class);
    }

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public function itemScores(): HasMany
    {
        return $this->hasMany(SessionItemScore::class, 'session_id');
    }
}
