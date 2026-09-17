<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiEditorialRun extends Model
{
    public const STATUS_QUEUED = 'queued';

    public const STATUS_RUNNING = 'running';

    public const STATUS_SUCCEEDED = 'succeeded';

    public const STATUS_FAILED = 'failed';

    public const STAGE_ENGLISH = 'english';

    public const STAGE_MALAYALAM = 'malayalam';

    public const STAGE_CLAIMS = 'claims';

    public const STAGE_COMPLETE = 'complete';

    protected $fillable = [
        'application_id',
        'profile_id',
        'requested_by_user_id',
        'provider',
        'model',
        'status',
        'stage',
        'package_tier',
        'input_fingerprint',
        'english_editorial_content_id',
        'malayalam_editorial_content_id',
        'error_code',
        'error_message',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
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

    public function englishContent(): BelongsTo
    {
        return $this->belongsTo(EditorialContent::class, 'english_editorial_content_id');
    }

    public function malayalamContent(): BelongsTo
    {
        return $this->belongsTo(EditorialContent::class, 'malayalam_editorial_content_id');
    }

    public function isSuccessful(): bool
    {
        return $this->status === self::STATUS_SUCCEEDED;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }
}
