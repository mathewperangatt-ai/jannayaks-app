<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SourceMaterial extends Model
{
    use HasFactory;

    protected $fillable = [
        'application_id',
        'user_id',
        'material_type',
        'storage_disk',
        'storage_path',
        'original_filename',
        'mime_type',
        'file_bytes',
        'client_hash_sha256',
        'admin_notes',
        'uploaded_at',
        'purged_at',
    ];

    protected function casts(): array
    {
        return [
            'file_bytes'  => 'integer',
            'uploaded_at' => 'datetime',
            'purged_at'   => 'datetime',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isPurged(): bool
    {
        return $this->purged_at !== null;
    }
}
