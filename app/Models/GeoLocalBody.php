<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GeoLocalBody extends Model
{
    protected $fillable = [
        'district_id',
        'body_code',
        'type',
        'name',
        'ward_count',
    ];

    protected function casts(): array
    {
        return [
            'ward_count' => 'int',
        ];
    }

    /** @return BelongsTo<GeoDistrict> */
    public function district(): BelongsTo
    {
        return $this->belongsTo(GeoDistrict::class, 'district_id');
    }

    /** @return HasMany<GeoWard> */
    public function wards(): HasMany
    {
        return $this->hasMany(GeoWard::class, 'local_body_id');
    }
}
