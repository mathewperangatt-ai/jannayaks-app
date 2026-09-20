<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProfileGeography extends Model
{
    protected $fillable = [
        'profile_id',
        'country_code',
        'state_region_name',
        'district_id',
        'local_body_id',
        'ward_id',
        'locality_place',
        'postal_code',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'country_code' => 'IN',
        'state_region_name' => 'Keralam',
    ];

    protected static function booted(): void
    {
        static::saving(function (ProfileGeography $geography): void {
            $geography->state_region_name = self::normalizedStateName($geography->state_region_name);
            if (! filled($geography->country_code)) {
                $geography->country_code = GeoState::currentCountryCode();
            }
        });
    }

    public static function normalizedStateName(?string $value): string
    {
        $name = trim((string) $value);
        if ($name === '' || strcasecmp($name, 'Kerala') === 0) {
            return GeoState::currentDisplayName();
        }

        return $name;
    }

    /** @return BelongsTo<Profile> */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class, 'profile_id');
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
