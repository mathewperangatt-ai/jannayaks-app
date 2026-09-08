<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InMemoriamEditorialContent extends Model
{
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

    protected function casts(): array
    {
        return [
            'version_number' => 'int',
            'ai_generated'   => 'bool',
        ];
    }

    /** @return BelongsTo<InMemoriamProfile> */
    public function inMemoriamProfile(): BelongsTo
    {
        return $this->belongsTo(InMemoriamProfile::class, 'in_memoriam_profile_id');
    }

    /** @return BelongsTo<User> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    /** @return BelongsTo<User> */
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_id');
    }
}
