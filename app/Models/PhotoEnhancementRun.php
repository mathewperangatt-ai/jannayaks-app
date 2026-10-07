<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Provenance for one AI photo-enhancement attempt.
 *
 * Independent from editorial AI provenance (AiEditorialRun) by design.
 * A partial unique index (one_active_per_source) guarantees at most one
 * active run — queued/processing/completed — per source photo.
 */
class PhotoEnhancementRun extends Model
{
    public const STATUS_QUEUED = 'queued';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_KEPT_ORIGINAL = 'kept_original';

    public const STATUS_DISCARDED = 'discarded';

    public const STATUS_CANCELLED = 'cancelled';

    public const ACTIVE_STATUSES = [
        self::STATUS_QUEUED,
        self::STATUS_PROCESSING,
        self::STATUS_COMPLETED,
    ];

    protected $fillable = [
        'source_media_id',
        'candidate_media_id',
        'provider',
        'model',
        'status',
        'error_message',
        'retry_count',
        'requested_by_user_id',
        'decided_by_user_id',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'retry_count' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<MediaItem, self> */
    public function source(): BelongsTo
    {
        return $this->belongsTo(MediaItem::class, 'source_media_id');
    }

    /** @return BelongsTo<MediaItem, self> */
    public function candidate(): BelongsTo
    {
        return $this->belongsTo(MediaItem::class, 'candidate_media_id');
    }

    public function isActive(): bool
    {
        return in_array($this->status, self::ACTIVE_STATUSES, true);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_QUEUED => 'Queued',
            self::STATUS_PROCESSING => 'Processing',
            self::STATUS_COMPLETED => 'Ready for review',
            self::STATUS_FAILED => 'Enhancement failed',
            self::STATUS_ACCEPTED => 'Enhanced photo accepted',
            self::STATUS_KEPT_ORIGINAL => 'Original kept',
            self::STATUS_DISCARDED => 'Candidate discarded',
            self::STATUS_CANCELLED => 'Cancelled',
            default => (string) $this->status,
        };
    }
}
