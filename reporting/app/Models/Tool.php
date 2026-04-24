<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tool extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'slug',
        'label',
        'sort_order',
    ];

    public function categories(): HasMany
    {
        return $this->hasMany(ToolCategory::class);
    }

    public function evaluationItems(): HasMany
    {
        return $this->hasMany(EvaluationItem::class);
    }

    public function evaluationSessions(): HasMany
    {
        return $this->hasMany(EvaluationSession::class);
    }
}
