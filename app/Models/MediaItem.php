<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class MediaItem extends Model
{
    public const TYPE_PROFILE_PHOTO = 'profile_photo';

    public const TYPE_GALLERY_IMAGE = 'gallery_image';

    public const TYPE_DOCUMENT = 'document';

    public const TYPE_OTHER = 'other';

    public const PRIVACY_PUBLIC = 'public';

    public const PRIVACY_UNLISTED = 'unlisted';

    public const PRIVACY_PRIVATE = 'private';

    public const REVIEW_PENDING = 'pending_review';

    public const REVIEW_APPROVED = 'approved';

    public const REVIEW_REJECTED = 'rejected';

    protected $fillable = [
        'mediable_type',
        'mediable_id',
        'media_type',
        'storage_path_key',
        'disk',
        'caption',
        'alt_text',
        'display_order',
        'is_primary',
        'uploaded_by_id',
        'privacy',
        'review_status',
        'reviewed_by_user_id',
        'reviewed_at',
        'review_note',
        'mime_type',
        'size_bytes',
        'width',
        'height',
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'int',
            'width' => 'int',
            'height' => 'int',
            'display_order' => 'int',
            'is_primary' => 'boolean',
            'reviewed_at' => 'datetime',
        ];
    }

    /** @return MorphTo<Model, self> */
    public function mediable(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<User, self> */
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_id');
    }

    /** @return BelongsTo<User, self> */
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function isApprovedForPublicDisplay(): bool
    {
        return $this->media_type === self::TYPE_PROFILE_PHOTO
            && $this->review_status === self::REVIEW_APPROVED
            && $this->privacy === self::PRIVACY_PUBLIC
            && filled($this->storage_path_key);
    }

    /** @deprecated use isApprovedForPublicDisplay */
    public function isPublicProfilePhoto(): bool
    {
        return $this->isApprovedForPublicDisplay();
    }
}
