<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = [
        'membership_id',
        'in_memoriam_profile_id',
        'profile_id',
        'transaction_reference',
        'gateway',
        'item_type',
        'amount',
        'currency',
        'status',
        'paid_at',
        'captured_at',
        'method',
        'gateway_event_id',
        'gateway_payment_id',
        'payment_method_type',
        'card_last4',
        'error_code',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'amount'      => 'decimal:2',
            'paid_at'     => 'datetime',
            'captured_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Membership> */
    public function membership(): BelongsTo
    {
        return $this->belongsTo(Membership::class, 'membership_id');
    }

    /** @return BelongsTo<InMemoriamProfile> */
    public function inMemoriamProfile(): BelongsTo
    {
        return $this->belongsTo(InMemoriamProfile::class, 'in_memoriam_profile_id');
    }

    /** @return BelongsTo<Profile> */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class, 'profile_id');
    }
}
