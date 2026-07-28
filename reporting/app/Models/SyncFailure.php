<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SyncFailure extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'db_name',
        'doc_id',
        'message',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }
}
