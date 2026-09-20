<?php

namespace App\Models;

use Database\Factories\SlugReservationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SlugReservation extends Model
{
    /** @use HasFactory<SlugReservationFactory> */
    use HasFactory;

    public const SOURCE_ASSIGNED = 'assigned';

    public const SOURCE_HISTORICAL = 'historical';

    public const SOURCE_IN_MEMORIAM = 'in_memoriam';

    protected $fillable = [
        'slug',
        'profile_id',
        'application_id',
        'source',
    ];

    /** @return BelongsTo<Profile, $this> */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }

    /** @return BelongsTo<Application, $this> */
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }
}
