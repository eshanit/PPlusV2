<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SessionItemScore extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'session_id',
        'item_id',
        'mentee_score',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'mentee_score' => 'integer',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(EvaluationSession::class, 'session_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(EvaluationItem::class, 'item_id');
    }
}
