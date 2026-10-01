<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Recommendation extends Model
{
    protected $fillable = [
        'recommender_name',
        'recommender_contact',
        'recommended_name',
        'recommended_location',
        'recommended_role',
        'reason',
        'supporting_info',
        'acknowledged_terms',
        'ip_hash',
        'notified_at',
    ];

    protected function casts(): array
    {
        return [
            'acknowledged_terms' => 'boolean',
            'notified_at' => 'datetime',
        ];
    }
}
