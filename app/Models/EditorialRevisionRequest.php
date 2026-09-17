<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EditorialRevisionRequest extends Model
{
    public const TYPE_REVISION = 'revision';

    public const TYPE_FACTUAL_CORRECTION = 'factual_correction';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'application_id',
        'profile_id',
        'requested_by_user_id',
        'round_number',
        'request_type',
        'status',
        'request_text',
        'preview_english_editorial_content_id',
        'preview_malayalam_editorial_content_id',
        'processed_by_user_id',
        'processed_at',
        'staff_notes',
    ];

    protected function casts(): array
    {
        return [
            'round_number' => 'integer',
            'processed_at' => 'datetime',
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

    public function previewEnglishContent(): BelongsTo
    {
        return $this->belongsTo(EditorialContent::class, 'preview_english_editorial_content_id');
    }

    public function consumesIncludedRevisionRound(): bool
    {
        return $this->request_type === self::TYPE_REVISION;
    }
}
