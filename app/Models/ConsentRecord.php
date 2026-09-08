<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConsentRecord extends Model
{
    protected $fillable = [
        'user_id',
        'consent_key',
        'consented',
        'action_at',
        'notice_version',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'consented' => 'bool',
            'action_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
