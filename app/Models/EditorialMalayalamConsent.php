<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EditorialMalayalamConsent extends Model
{
    protected $fillable = [
        'application_id',
        'profile_id',
        'consented_by_user_id',
        'malayalam_editorial_content_id',
        'content_sha256',
        'version_number',
        'consented_at',
        'invalidated_at',
        'invalidation_reason',
    ];

    protected function casts(): array
    {
        return [
            'version_number' => 'int',
            'consented_at' => 'datetime',
            'invalidated_at' => 'datetime',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function malayalamContent(): BelongsTo
    {
        return $this->belongsTo(EditorialContent::class, 'malayalam_editorial_content_id');
    }

    public function isActive(): bool
    {
        return $this->invalidated_at === null;
    }

    public function matches(EditorialContent $content): bool
    {
        return (int) $this->malayalam_editorial_content_id === (int) $content->id
            && (int) $this->version_number === (int) $content->version_number
            && hash_equals((string) $this->content_sha256, self::fingerprint($content));
    }

    public static function fingerprint(EditorialContent $content): string
    {
        return hash('sha256', implode("\n", [
            (string) $content->language,
            (string) $content->version_number,
            (string) $content->source_editorial_content_id,
            (string) $content->title,
            (string) $content->summary,
            (string) $content->body,
        ]));
    }
}
