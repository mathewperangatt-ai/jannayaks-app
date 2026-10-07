<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EditorialRevisionRequest extends Model
{
    public const TYPE_REVISION = 'revision';

    public const TYPE_FACTUAL_CORRECTION = 'factual_correction';

    public const TYPE_PUBLISHED_UPDATE = 'published_update';

    public const BILLING_COMPLIMENTARY = 'complimentary';

    public const BILLING_PAID = 'paid';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_CUSTOMER_PREVIEW = 'customer_preview';

    public const STATUS_CUSTOMER_APPROVED = 'customer_approved';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'application_id',
        'profile_id',
        'requested_by_user_id',
        'round_number',
        'request_type',
        'billing_classification',
        'eligibility_published_on',
        'next_eligible_on',
        'status',
        'request_text',
        'preview_english_editorial_content_id',
        'preview_malayalam_editorial_content_id',
        'resulting_english_editorial_content_id',
        'resulting_malayalam_editorial_content_id',
        'approved_english_editorial_content_id',
        'approved_malayalam_editorial_content_id',
        'maintenance_preview_released_at',
        'customer_correction_text',
        'processed_by_user_id',
        'processed_at',
        'staff_notes',
    ];

    protected function casts(): array
    {
        return [
            'round_number' => 'integer',
            'processed_at' => 'datetime',
            'eligibility_published_on' => 'date',
            'next_eligible_on' => 'date',
            'maintenance_preview_released_at' => 'datetime',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by_user_id');
    }

    public function resultingEnglishContent(): BelongsTo
    {
        return $this->belongsTo(EditorialContent::class, 'resulting_english_editorial_content_id');
    }

    public function resultingMalayalamContent(): BelongsTo
    {
        return $this->belongsTo(EditorialContent::class, 'resulting_malayalam_editorial_content_id');
    }

    public function approvedEnglishContent(): BelongsTo
    {
        return $this->belongsTo(EditorialContent::class, 'approved_english_editorial_content_id');
    }

    public function approvedMalayalamContent(): BelongsTo
    {
        return $this->belongsTo(EditorialContent::class, 'approved_malayalam_editorial_content_id');
    }

    public function previewEnglishContent(): BelongsTo
    {
        return $this->belongsTo(EditorialContent::class, 'preview_english_editorial_content_id');
    }

    public function consumesIncludedRevisionRound(): bool
    {
        return $this->request_type === self::TYPE_REVISION;
    }

    public function isPublishedUpdate(): bool
    {
        return $this->request_type === self::TYPE_PUBLISHED_UPDATE;
    }

    /**
     * The maintenance cycle is awaiting editorial preparation (AI draft,
     * editorial edit, or a customer minor-correction round).
     */
    public function isAwaitingPreparation(): bool
    {
        return $this->isPublishedUpdate()
            && in_array($this->status, [self::STATUS_SUBMITTED, self::STATUS_IN_PROGRESS], true);
    }
}
