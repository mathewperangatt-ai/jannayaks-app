<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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

    /** @return HasMany<GeoDistrict> */
    public function districts(): HasMany
    {
        return $this->hasMany(GeoDistrict::class, 'state_code');
    }
}
