<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Editorial photograph placement: where an approved photograph appears in
 * the publication-ready presentation. The AI recommends; administrators
 * decide. Never touches the underlying MediaItem asset.
 */
class EditorialPhotoPlacement extends Model
{
    public const TYPE_HERO = 'hero';

    public const TYPE_INLINE = 'inline';

    public const TYPE_GALLERY = 'gallery';

    public const STATUS_SUGGESTED = 'suggested';

    public const STATUS_APPROVED = 'approved';

    public const SOURCE_AI = 'ai';

    public const SOURCE_ADMIN = 'admin';

    public const ANCHOR_BIOGRAPHY = 'biography';

    public const ANCHOR_CAREER = 'career';

    public const ANCHOR_GALLERY = 'gallery';

    public const PLACEMENT_TYPES = [self::TYPE_HERO, self::TYPE_INLINE, self::TYPE_GALLERY];

    public const SECTION_ANCHORS = [self::ANCHOR_BIOGRAPHY, self::ANCHOR_CAREER, self::ANCHOR_GALLERY];

    protected $fillable = [
        'profile_id',
        'media_item_id',
        'application_id',
        'placement_type',
        'section_anchor',
        'display_order',
        'caption_en',
        'caption_ml',
        'status',
        'source',
        'placement_run_id',
        'decided_by_user_id',
        'decided_at',
        'ai_rationale',
    ];

    protected function casts(): array
    {
        return [
            'display_order' => 'integer',
            'decided_at' => 'datetime',
        ];
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }

    public function mediaItem(): BelongsTo
    {
        return $this->belongsTo(MediaItem::class, 'media_item_id');
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(PhotoPlacementRun::class, 'placement_run_id');
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by_user_id');
    }

    /** The caption for the active presentation language, or null when none was editorially approved for it. */
    public function captionFor(string $language): ?string
    {
        $caption = $language === 'ml' ? $this->caption_ml : $this->caption_en;

        return ($caption !== null && trim($caption) !== '') ? trim((string) $caption) : null;
    }
}
