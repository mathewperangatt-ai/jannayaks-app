<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class SlugRedirect extends Model
{
    use HasFactory;

    protected $fillable = [
        'old_slug',
        'new_slug',
        'redirectable_type',
        'redirectable_id',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
        ];
    }

    public function redirectable(): MorphTo
    {
        return $this->morphTo();
    }
}
