<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EditorialContent extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_ARCHIVED = 'archived';

    public const LANGUAGE_EN = 'en';

    public const LANGUAGE_ML = 'ml';

    /**
     * Internal editorial review flags (Master Editorial Specification §10).
     * A flag means human review is required — never that content is forbidden.
     */
    public const FLAG_CLAIM_REVIEW = 'CLAIM_REVIEW';

    public const FLAG_SOURCE_CONFLICT = 'SOURCE_CONFLICT';

    public const FLAG_SENSITIVE_PERSONAL_CONTENT = 'SENSITIVE_PERSONAL_CONTENT';

    public const FLAG_UNCERTAIN_DATE = 'UNCERTAIN_DATE';

    public const FLAG_STRONG_CLAIM = 'STRONG_CLAIM';

    public const FLAG_POLITICAL_CONTENT_REVIEW = 'POLITICAL_CONTENT_REVIEW';

    public const FLAG_IDENTITY_SENSITIVITY_REVIEW = 'IDENTITY_SENSITIVITY_REVIEW';

    public const FLAG_QUOTE_VERIFICATION = 'QUOTE_VERIFICATION';

    public const FLAG_THIRD_PARTY_PRIVACY_REVIEW = 'THIRD_PARTY_PRIVACY_REVIEW';

    public const ALLOWED_REVIEW_FLAGS = [
        self::FLAG_CLAIM_REVIEW,
        self::FLAG_SOURCE_CONFLICT,
        self::FLAG_SENSITIVE_PERSONAL_CONTENT,
        self::FLAG_UNCERTAIN_DATE,
        self::FLAG_STRONG_CLAIM,
        self::FLAG_POLITICAL_CONTENT_REVIEW,
        self::FLAG_IDENTITY_SENSITIVITY_REVIEW,
        self::FLAG_QUOTE_VERIFICATION,
        self::FLAG_THIRD_PARTY_PRIVACY_REVIEW,
    ];

    protected $fillable = [
        'profile_id',
        'source_editorial_content_id',
        'generation_run_id',
        'language',
        'status',
        'version_number',
        'title',
        'body',
        'summary',
        'source_material',
        'ai_generated',
        'review_flags',
        'created_by_id',
        'reviewed_by_id',
        'review_comment',
    ];

    protected function casts(): array
    {
        return [
            'ai_generated' => 'bool',
            'version_number' => 'int',
            'review_flags' => 'array',
        ];
    }

    /**
     * Filter an arbitrary flag list down to the approved vocabulary,
     * de-duplicated, order-preserving. Single enforcement point for
     * AI-emitted and staff-provided review flags.
     *
     * @param  mixed  $flags
     * @return list<string>
     */
    public static function sanitizeReviewFlags($flags): array
    {
        if (! is_array($flags)) {
            return [];
        }

        $allowed = [];
        foreach ($flags as $flag) {
            if (is_string($flag)) {
                $flag = trim($flag);
                if ($flag !== '' && in_array($flag, self::ALLOWED_REVIEW_FLAGS, true) && ! in_array($flag, $allowed, true)) {
                    $allowed[] = $flag;
                }
            }
        }

        return $allowed;
    }

    /** @return BelongsTo<Profile, $this> */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class, 'profile_id');
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    /** @return BelongsTo<User, $this> */
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_id');
    }

    /** @return BelongsTo<self, $this> */
    public function sourceEditorialContent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'source_editorial_content_id');
    }

    /** @return BelongsTo<AiEditorialRun, $this> */
    public function generationRun(): BelongsTo
    {
        return $this->belongsTo(AiEditorialRun::class, 'generation_run_id');
    }

    /** @return HasMany<EditorialClaimTrace, $this> */
    public function claimTraces(): HasMany
    {
        return $this->hasMany(EditorialClaimTrace::class)->orderBy('sort_order');
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isUsableEditorialDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isIncompleteFailedGeneration(): bool
    {
        if ($this->status !== self::STATUS_ARCHIVED || ! $this->ai_generated || ! $this->generation_run_id) {
            return false;
        }

        $run = $this->generationRun;

        return $run !== null && $run->isFailed();
    }
}
