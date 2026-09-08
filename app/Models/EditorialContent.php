<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EditorialContent extends Model
{
    protected $fillable = [
        'profile_id',
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
            'ai_generated'   => 'bool',
            'version_number' => 'int',
        ];
    }

    /** @return BelongsTo<Profile> */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class, 'profile_id');
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
