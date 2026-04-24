<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GapEntry extends Model
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
        'identified_at',
        'description',
        'domains',
        'covered_in_mentorship',
        'covering_later',
        'timeline',
        'supervision_level',
        'resolution_note',
        'resolved_at',
        'couchdb_rev',
        'synced_at',
        'created_at',
        'updated_at',
    ];

    protected function casts(): array
    {
        return [
            'identified_at' => 'date',
            'resolved_at' => 'date',
            'domains' => 'array',
            'covered_in_mentorship' => 'boolean',
            'covering_later' => 'boolean',
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
}
