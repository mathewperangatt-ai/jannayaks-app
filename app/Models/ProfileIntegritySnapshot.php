<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProfileIntegritySnapshot extends Model
{
    protected $fillable = [
        'profile_id',
        'application_id',
        'slug',
        'display_name',
        'profession',
        'english_editorial_content_id',
        'english_content_hash',
        'malayalam_editorial_content_id',
        'malayalam_content_hash',
        'primary_photo_media_id',
        'primary_photo_disk',
        'primary_photo_key',
        'primary_photo_sha256',
        'primary_photo_size_bytes',
        'photos',
        'public_offices',
        'video_links',
        'snapshot_hash',
        'source_event',
        'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'photos' => 'array',
            'public_offices' => 'array',
            'video_links' => 'array',
            'verified_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Profile> */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class, 'profile_id');
    }

    /**
     * Canonical monitored payload (everything compared by the watchdog).
     * Provenance fields (source_event, verified_at, timestamps) are excluded.
     *
     * @return array<string, mixed>
     */
    public function canonicalPayload(): array
    {
        return [
            'slug' => $this->slug,
            'display_name' => $this->display_name,
            'profession' => $this->profession,
            'english_editorial_content_id' => $this->english_editorial_content_id !== null ? (int) $this->english_editorial_content_id : null,
            'english_content_hash' => $this->english_content_hash,
            'malayalam_editorial_content_id' => $this->malayalam_editorial_content_id !== null ? (int) $this->malayalam_editorial_content_id : null,
            'malayalam_content_hash' => $this->malayalam_content_hash,
            'primary_photo_media_id' => $this->primary_photo_media_id !== null ? (int) $this->primary_photo_media_id : null,
            'primary_photo_disk' => $this->primary_photo_disk,
            'primary_photo_key' => $this->primary_photo_key,
            'primary_photo_sha256' => $this->primary_photo_sha256,
            'primary_photo_size_bytes' => $this->primary_photo_size_bytes !== null ? (int) $this->primary_photo_size_bytes : null,
            'photos' => $this->photos ?? [],
            'public_offices' => $this->public_offices ?? [],
            'video_links' => $this->video_links ?? [],
        ];
    }
}
