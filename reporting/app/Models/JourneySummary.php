<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JourneySummary extends Model
{
    protected $table = 'v_journey_summary';

    public $incrementing = false;

    public $timestamps = false;

    protected $primaryKey = 'evaluation_group_id';

    protected $keyType = 'string';

    protected function casts(): array
    {
        return [
            'latest_session_date' => 'date',
            'basic_competent_at' => 'date',
            'first_full_competency_date' => 'date',
            'last_updated' => 'datetime',
            'basic_competent' => 'boolean',
            'fully_competent' => 'boolean',
            'latest_avg_score' => 'float',
            'sessions_to_basic_competence' => 'integer',
            'sessions_to_full_competence' => 'integer',
            'days_to_basic_competence' => 'integer',
            'days_to_full_competence' => 'integer',
            'total_sessions' => 'integer',
            'latest_scored_items' => 'integer',
            'open_gaps' => 'integer',
            'resolved_gaps' => 'integer',
        ];
    }
}
