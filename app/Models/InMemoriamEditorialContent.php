<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InMemoriamEditorialContent extends Model
{
    public const LANGUAGE_EN = 'en';

    public const LANGUAGE_ML = 'ml';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_ARCHIVED = 'archived';

    protected $fillable = [
        'in_memoriam_profile_id',
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

    protected $attributes = [
        'ai_generated' => false,
        'status' => self::STATUS_DRAFT,
        'version_number' => 1,
        'title' => '',
        'body' => '',
        'summary' => '',
        'source_material' => '',
    ];

    protected function casts(): array
    {
        return [
            'version_number' => 'int',
            'ai_generated' => 'bool',
        ];
    }

    /** @return BelongsTo<InMemoriamProfile, $this> */
    public function inMemoriamProfile(): BelongsTo
    {
        return $this->belongsTo(InMemoriamProfile::class, 'in_memoriam_profile_id');
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
}
