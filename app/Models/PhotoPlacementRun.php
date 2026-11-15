<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Provenance record for one AI photograph-placement recommendation run.
 * Created only by an explicit administrator action; identical inputs are
 * reused instead of re-analysing unchanged photographs.
 */
class PhotoPlacementRun extends Model
{
    public const STATUS_RUNNING = 'running';

    public const STATUS_SUCCEEDED = 'succeeded';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'application_id',
        'profile_id',
        'status',
        'provider',
        'model',
        'input_fingerprint',
        'english_editorial_content_id',
        'recommendations',
        'warnings',
        'error_code',
        'error_message',
        'requested_by_user_id',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'recommendations' => 'array',
            'warnings' => 'array',
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

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }
}
