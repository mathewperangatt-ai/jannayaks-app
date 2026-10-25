<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Recommendation extends Model
{
    protected $fillable = [
        'recommender_name',
        'recommender_contact',
        'recommended_name',
        'recommended_email',
        'recommended_location',
        'recommended_role',
        'reason',
        'supporting_info',
        'acknowledged_terms',
        'share_recommender_name',
        'consent_notice_version',
        'ip_hash',
        'notified_at',
        'invitation_sent_at',
        'invitation_failed_at',
    ];

    protected function casts(): array
    {
        return [
            'acknowledged_terms' => 'boolean',
            'share_recommender_name' => 'boolean',
            'notified_at' => 'datetime',
            'invitation_sent_at' => 'datetime',
            'invitation_failed_at' => 'datetime',
        ];
    }
}
