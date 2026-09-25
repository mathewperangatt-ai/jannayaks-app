<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntegrityIncident extends Model
{
    public const TYPE_PHOTOGRAPH_REFERENCE = 'photograph_reference';

    public const TYPE_PHOTOGRAPH_OBJECT = 'photograph_object';

    public const TYPE_EDITORIAL = 'editorial';

    public const TYPE_IDENTITY = 'identity';

    public const TYPE_OFFICES = 'public_offices';

    public const TYPE_VIDEO_LINKS = 'video_links';

    public const STATUS_OPEN = 'open';

    protected $fillable = [
        'profile_id',
        'run_id',
        'type',
        'mode',
        'detection_reason',
        'expected_state',
        'observed_state',
        'detection_fingerprint',
        'correlated_event_ids',
        'detected_at',
    ];

    protected function casts(): array
    {
        return [
            'expected_state' => 'array',
            'observed_state' => 'array',
            'correlated_event_ids' => 'array',
            'detected_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Profile> */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class, 'profile_id');
    }
}
