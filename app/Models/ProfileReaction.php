<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProfileReaction extends Model
{
    public const REACTION_LIKE = 'like';

    public const REACTION_APPLAUD = 'applaud';

    public const REACTIONS = [self::REACTION_LIKE, self::REACTION_APPLAUD];

    protected $fillable = [
        'profile_id',
        'user_id',
        'reaction',
        'broad_location',
    ];

    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Display label for the owner's private view: "Mr. Arun K. · Kollam — Liked your profile".
     */
    public function ownerDisplayLine(): string
    {
        $name = trim((string) ($this->user?->name ?? 'A visitor'));
        $location = trim((string) $this->broad_location);
        $verb = $this->reaction === self::REACTION_APPLAUD ? 'Applauded your profile' : 'Liked your profile';

        return $location !== '' ? $name.' · '.$location.' — '.$verb : $name.' — '.$verb;
    }
}
