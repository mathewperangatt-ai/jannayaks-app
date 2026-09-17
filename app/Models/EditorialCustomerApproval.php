<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EditorialCustomerApproval extends Model
{
    protected $fillable = [
        'application_id',
        'profile_id',
        'approved_by_user_id',
        'english_editorial_content_id',
        'malayalam_editorial_content_id',
        'approved_at',
        'invalidated_at',
        'invalidation_reason',
    ];

    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
            'invalidated_at' => 'datetime',
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

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function englishContent(): BelongsTo
    {
        return $this->belongsTo(EditorialContent::class, 'english_editorial_content_id');
    }

    public function malayalamContent(): BelongsTo
    {
        return $this->belongsTo(EditorialContent::class, 'malayalam_editorial_content_id');
    }

    public function isActive(): bool
    {
        return $this->invalidated_at === null;
    }
}
