<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InMemoriamGeography extends Model
{
    protected $fillable = [
        'in_memoriam_profile_id',
        'country_code',
        'state_region_name',
        'district_id',
        'local_body_id',
        'ward_id',
        'locality_place',
        'postal_code',
    ];

    /** @return BelongsTo<InMemoriamProfile> */
    public function inMemoriamProfile(): BelongsTo
    {
        return $this->belongsTo(InMemoriamProfile::class, 'in_memoriam_profile_id');
    }

    /** @return BelongsTo<GeoDistrict> */
    public function district(): BelongsTo
    {
        return $this->belongsTo(GeoDistrict::class, 'district_id');
    }

    /** @return BelongsTo<GeoLocalBody> */
    public function localBody(): BelongsTo
    {
        return $this->belongsTo(GeoLocalBody::class, 'local_body_id');
    }

    /** @return BelongsTo<GeoWard> */
    public function ward(): BelongsTo
    {
        return $this->belongsTo(GeoWard::class, 'ward_id');
    }
}
