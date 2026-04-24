<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ToolCategory extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'tool_id',
        'name',
        'sort_order',
    ];

    public function tool(): BelongsTo
    {
        return $this->belongsTo(Tool::class);
    }

    public function evaluationItems(): HasMany
    {
        return $this->hasMany(EvaluationItem::class, 'category_id');
    }
}
