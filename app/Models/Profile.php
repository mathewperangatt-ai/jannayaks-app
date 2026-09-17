<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Profile extends Model
{
    protected $fillable = [
        'user_id',
        'representative_user_id',
        'status',
        'full_name',
        'display_name',
        'date_of_birth',
        'gender',
        'place_of_birth',
        'bio_headline',
        'slug',
        'slug_generated_at',
        'slug_changed_at',
        'previous_slug',
        'profession',
        'display_phone_consent',
        'display_email_consent',
        'submitted_at',
        'approved_at',
        'published_at',
        'rejected_at',
        'suspended_at',
        'unpublished_at',
        'erasure_requested_at',
        'erasure_completed_at',
    ];

    protected function casts(): array
    {
        return [
            'display_phone_consent' => 'boolean',
            'display_email_consent' => 'boolean',
            'date_of_birth' => 'date',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'published_at' => 'datetime',
            'rejected_at' => 'datetime',
            'suspended_at' => 'datetime',
            'unpublished_at' => 'datetime',
            'erasure_requested_at' => 'datetime',
            'erasure_completed_at' => 'datetime',
            'slug_generated_at' => 'datetime',
            'slug_changed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return BelongsTo<User> */
    public function representativeUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'representative_user_id');
    }

    /** @return HasOne<ProfileGeography> */
    public function geography(): HasOne
    {
        return $this->hasOne(ProfileGeography::class, 'profile_id');
    }

    /** @return HasMany<ProfilePublicOffice> */
    public function publicOffices(): HasMany
    {
        return $this->hasMany(ProfilePublicOffice::class, 'profile_id')->orderBy('sort_order');
    }

    /** @return HasMany<ProfileVerification> */
    public function verifications(): HasMany
    {
        return $this->hasMany(ProfileVerification::class, 'profile_id');
    }

    /** @return HasMany<EditorialContent> */
    public function editorialContents(): HasMany
    {
        return $this->hasMany(EditorialContent::class, 'profile_id');
    }

    /** @return HasOne<Membership> */
    public function membership(): HasOne
    {
        return $this->hasOne(Membership::class, 'profile_id');
    }

    /** @return HasMany<Payment> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'profile_id');
    }

    /** @return HasOne<Application> */
    public function application(): HasOne
    {
        return $this->hasOne(Application::class, 'profile_id');
    }

    /** @return MorphMany<SlugRedirect> */
    public function slugRedirects(): MorphMany
    {
        return $this->morphMany(SlugRedirect::class, 'redirectable');
    }

    /** @return MorphMany<MediaItem> */
    public function media(): MorphMany
    {
        return $this->morphMany(MediaItem::class, 'mediable')->orderBy('display_order');
    }

    public function isPubliclyListed(): bool
    {
        return $this->status === 'published'
            && $this->published_at !== null
            && $this->unpublished_at === null
            && $this->suspended_at === null
            && $this->erasure_completed_at === null;
    }
}
