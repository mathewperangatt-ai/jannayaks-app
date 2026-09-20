<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GeoState extends Model
{
    protected $primaryKey = 'code';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'code',
        'name',
        'country_name',
    ];

    public static function currentDisplayName(): string
    {
        return (string) config('jannayaks.geography.current_state_name');
    }

    public static function currentCountryCode(): string
    {
        return (string) config('jannayaks.geography.current_country_code');
    }

    public static function currentCountryName(): string
    {
        return (string) config('jannayaks.geography.current_country_name');
    }

    /** @return HasMany<GeoDistrict> */
    public function districts(): HasMany
    {
        return $this->hasMany(GeoDistrict::class, 'state_code');
    }
}
