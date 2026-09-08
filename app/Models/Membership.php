<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Membership extends Model
{
    protected $fillable = [
        'profile_id',
        'status',
        'tier',
        'starts_on',
        'ends_on',
        'renewal_due_on',
        'auto_renew',
    ];

    protected function casts(): array
    {
        return [
            'starts_on'      => 'date',
            'ends_on'        => 'date',
            'renewal_due_on' => 'date',
            'auto_renew'     => 'bool',
        ];
    }

    /** @return BelongsTo<Profile> */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class, 'profile_id');
    }

    /** @return HasMany<Payment> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'membership_id');
    }
}
