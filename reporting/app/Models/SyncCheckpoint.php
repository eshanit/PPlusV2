<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SyncCheckpoint extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $primaryKey = 'db_name';

    protected $keyType = 'string';

    protected $fillable = [
        'db_name',
        'last_seq',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'last_synced_at' => 'datetime',
        ];
    }
}
