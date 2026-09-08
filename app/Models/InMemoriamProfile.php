<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class InMemoriamProfile extends Model
{
    protected $fillable = [
        'commissioner_user_id',
        'status',
        'commissioner_contact_name',
        'commissioner_contact_mobile',
        'commissioner_contact_email',
        'commissioner_relation',
        'commissioner_display_consent',
        'deceased_full_name',
        'deceased_display_name',
        'deceased_gender',
        'deceased_date_of_birth',
        'deceased_place_of_birth',
        'deceased_date_of_death',
        'deceased_place_of_death',
        'profession',
        'bio_headline',
        'commission_amount',
        'commission_gst_amount',
        'commission_currency',
        'commission_paid_at',
        'submitted_at',
        'editorial_reviewed_at',
        'commissioner_approved_at',
        'published_at',
        'hosting_starts_on',
        'hosting_ends_on',
        'renewal_due_on',
        'is_sealed',
        'last_admin_corrected_at',
        'last_admin_corrected_by_user_id',
        'admin_correction_notes',
    ];

    protected function casts(): array
    {
        return [
            'commissioner_display_consent'    => 'bool',
            'deceased_date_of_birth'          => 'date',
            'deceased_date_of_death'          => 'date',
            'commission_amount'               => 'decimal:2',
            'commission_gst_amount'           => 'decimal:2',
            'commission_paid_at'              => 'datetime',
            'submitted_at'                    => 'datetime',
            'editorial_reviewed_at'           => 'datetime',
            'commissioner_approved_at'        => 'datetime',
            'published_at'                    => 'datetime',
            'hosting_starts_on'               => 'date',
            'hosting_ends_on'                 => 'date',
            'renewal_due_on'                  => 'date',
            'is_sealed'                       => 'bool',
            'last_admin_corrected_at'         => 'datetime',
        ];
    }

    /** @return BelongsTo<User> */
    public function commissionerUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'commissioner_user_id');
    }

    /** @return BelongsTo<User> */
    public function lastAdminCorrectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_admin_corrected_by_user_id');
    }

    /** @return HasOne<InMemoriamGeography> */
    public function geography(): HasOne
    {
        return $this->hasOne(InMemoriamGeography::class, 'in_memoriam_profile_id');
    }

    /** @return HasMany<InMemoriamPublicOffice> */
    public function publicOffices(): HasMany
    {
        return $this->hasMany(InMemoriamPublicOffice::class, 'in_memoriam_profile_id')
            ->orderBy('sort_order');
    }

    /** @return HasMany<InMemoriamEditorialContent> */
    public function editorialContents(): HasMany
    {
        return $this->hasMany(InMemoriamEditorialContent::class, 'in_memoriam_profile_id')
            ->orderBy('version_number');
    }

    /** @return MorphMany<MediaItem> */
    public function media(): MorphMany
    {
        return $this->morphMany(MediaItem::class, 'mediable')
            ->orderBy('display_order');
    }

    /** @return HasMany<Payment> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'in_memoriam_profile_id');
    }
}
