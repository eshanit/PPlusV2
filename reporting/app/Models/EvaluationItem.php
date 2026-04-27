<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EvaluationItem extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'tool_id',
        'category_id',
        'slug',
        'number',
        'title',
        'is_advanced',
        'is_critical',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_advanced' => 'boolean',
            'is_critical' => 'boolean',
        ];
    }

    public function tool(): BelongsTo
    {
        return $this->belongsTo(Tool::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ToolCategory::class, 'category_id');
    }

    public function sessionScores(): HasMany
    {
        return $this->hasMany(SessionItemScore::class, 'item_id');
    }
}
