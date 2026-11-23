<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A member-submitted replacement photograph awaiting Security Administrator
 * review. Stored separately from published media: submission, screening,
 * review and publication are fully auditable, and rejection has zero effect
 * on published content.
 */
class PendingPhotoReplacement extends Model
{
    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const SCREENING_CLEAR = 'clear';

    public const SCREENING_FLAGGED = 'flagged';

    protected $fillable = [
        'profile_id',
        'application_id',
        'member_user_id',
        'storage_path',
        'mime_type',
        'size_bytes',
        'width',
        'height',
        'sha256',
        'member_note',
        'screening_result',
        'screening_reasons',
        'manual_examination_confirmed',
        'examined_by_user_id',
        'status',
        'review_note',
        'reviewer_user_id',
        'reviewed_at',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'screening_reasons' => 'array',
            'reviewed_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Profile> */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class, 'profile_id');
    }

    /** @return BelongsTo<Application> */
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class, 'application_id');
    }

    /** @return BelongsTo<User> */
    public function member(): BelongsTo
    {
        return $this->belongsTo(User::class, 'member_user_id');
    }

    /** @return BelongsTo<User> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_user_id');
    }
}
