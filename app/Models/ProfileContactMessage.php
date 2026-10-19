<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProfileContactMessage extends Model
{
    protected $fillable = [
        'profile_id',
        'visitor_name',
        'visitor_mobile',
        'message',
        'ip_hash',
        'delivered_at',
    ];

    protected function casts(): array
    {
        return [
            'delivered_at' => 'datetime',
        ];
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }

    /**
     * Owner notification body per the approved wording. The visitor's
     * contact details are shown only to the owner, never publicly.
     */
    public function ownerNotificationText(): string
    {
        $mobileLine = filled($this->visitor_mobile)
            ? "\nMobile number provided: {$this->visitor_mobile}."
            : "\nNo mobile number was provided — please reply by email.";

        return sprintf(
            "%s would like to contact you regarding %s.%s\nYou may contact %s directly if you wish.",
            $this->visitor_name,
            $this->message,
            $mobileLine,
            $this->visitor_name,
        );
    }
}
