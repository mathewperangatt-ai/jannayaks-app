<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GeoDistrict extends Model
{
    protected $fillable = [
        'state_code',
        'name',
        'source_code',
    ];

    /** @return BelongsTo<GeoState> */
    public function state(): BelongsTo
    {
        return $this->belongsTo(GeoState::class, 'state_code');
    }

    /** @return HasMany<GeoLocalBody> */
    public function localBodies(): HasMany
    {
        return $this->hasMany(GeoLocalBody::class, 'district_id');
    }
}
