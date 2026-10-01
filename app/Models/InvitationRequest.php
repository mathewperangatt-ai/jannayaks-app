<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvitationRequest extends Model
{
    protected $fillable = [
        'name',
        'contact',
        'town',
        'role',
        'reason',
        'acknowledged_terms',
        'ip_hash',
        'notified_at',
    ];

    protected function casts(): array
    {
        return [
            'acknowledged_terms' => 'boolean',
            'notified_at' => 'datetime',
        ];
    }
}
