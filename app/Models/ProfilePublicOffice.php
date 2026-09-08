<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProfilePublicOffice extends Model
{
    protected $fillable = [
        'profile_id',
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
            'is_current' => 'bool',
            'started_on' => 'date',
            'ended_on'   => 'date',
            'sort_order' => 'int',
        ];
    }

    /** @return BelongsTo<Profile> */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class, 'profile_id');
    }
}
