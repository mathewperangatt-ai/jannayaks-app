<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProfileExternalLink extends Model
{
    public const TYPE_VIDEO = 'video';

    public const STATUS_PENDING_REVIEW = 'pending_review';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_REPLACED = 'replaced';

    protected $fillable = [
        'profile_id',
        'link_type',
        'label',
        'url',
        'status',
        'is_publicly_active',
        'submitted_by_user_id',
        'reviewed_by_user_id',
        'reviewed_at',
        'review_note',
        'replaces_link_id',
    ];

    protected function casts(): array
    {
        return [
            'is_publicly_active' => 'boolean',
            'reviewed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Profile, self> */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }

    /** @return BelongsTo<User, self> */
    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }

    /** @return BelongsTo<User, self> */
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    /** @return BelongsTo<ProfileExternalLink, self> */
    public function replaces(): BelongsTo
    {
        return $this->belongsTo(self::class, 'replaces_link_id');
    }

    public function isActiveVideo(): bool
    {
        return $this->link_type === self::TYPE_VIDEO
            && $this->status === self::STATUS_APPROVED
            && $this->is_publicly_active;
    }
}
