<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MembershipLifecycleEvent extends Model
{
    public const TYPE_REMINDER_BEFORE = 'reminder_before_expiry';

    public const TYPE_REMINDER_AFTER = 'reminder_after_expiry';

    public const TYPE_DEACTIVATED = 'deactivated';

    public const TYPE_RENEWED = 'renewed';

    public const TYPE_REACTIVATED = 'reactivated';

    public const TYPE_STARTED = 'membership_started';

    protected $fillable = [
        'membership_id',
        'event_key',
        'event_type',
        'recorded_at',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'recorded_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    /** @return BelongsTo<Membership, self> */
    public function membership(): BelongsTo
    {
        return $this->belongsTo(Membership::class);
    }
}
