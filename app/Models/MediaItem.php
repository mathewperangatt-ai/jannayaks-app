<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class MediaItem extends Model
{
    protected $fillable = [
        'mediable_type',
        'mediable_id',
        'media_type',
        'storage_path_key',
        'disk',
        'caption',
        'alt_text',
        'display_order',
        'uploaded_by_id',
        'privacy',
        'mime_type',
        'size_bytes',
        'width',
        'height',
    ];

    protected function casts(): array
    {
        return [
            'size_bytes'    => 'int',
            'width'         => 'int',
            'height'        => 'int',
            'display_order' => 'int',
        ];
    }

    /** @return MorphTo<Model,self> */
    public function mediable(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<User> */
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_id');
    }
}
