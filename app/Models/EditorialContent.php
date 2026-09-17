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
        'created_by_id',
        'reviewed_by_id',
        'review_comment',
    ];

    protected function casts(): array
    {
        return [
            'ai_generated' => 'bool',
            'version_number' => 'int',
        ];
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
