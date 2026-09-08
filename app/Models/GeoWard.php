<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GeoWard extends Model
{
    protected $fillable = [
        'local_body_id',
        'ward_code',
        'name',
        'pop_male',
        'pop_female',
        'pop_other',
        'pop_total',
    ];

    protected function casts(): array
    {
        return [
            'pop_male'   => 'int',
            'pop_female' => 'int',
            'pop_other'  => 'int',
            'pop_total'  => 'int',
        ];
    }

    /** @return BelongsTo<GeoLocalBody> */
    public function localBody(): BelongsTo
    {
        return $this->belongsTo(GeoLocalBody::class, 'local_body_id');
    }
}
