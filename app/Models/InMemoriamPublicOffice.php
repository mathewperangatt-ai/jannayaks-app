<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InMemoriamPublicOffice extends Model
{
    protected $fillable = [
        'in_memoriam_profile_id',
        'office_name',
        'where_location',
        'term_summary',
        'started_on',
        'ended_on',
        'is_current',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'started_on'  => 'date',
            'ended_on'    => 'date',
            'is_current'  => 'bool',
            'sort_order'  => 'int',
        ];
    }

    /** @return BelongsTo<InMemoriamProfile> */
    public function inMemoriamProfile(): BelongsTo
    {
        return $this->belongsTo(InMemoriamProfile::class, 'in_memoriam_profile_id');
    }
}
