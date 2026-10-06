<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Profile extends Model
{
    /**
     * Profile Reference Number (e.g. JN-7K4P2): compact, human-readable,
     * non-sequential, unique. Independent of the public slug and never used
     * for routing. Alphabet excludes visually ambiguous glyphs (I, L, O, 0, 1).
     */
    public const REFERENCE_PREFIX = 'JN-';

    public const REFERENCE_ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    public const REFERENCE_LENGTH = 5;

    private const MAX_REFERENCE_ATTEMPTS = 32;

    protected static function booted(): void
    {
        // Canonical reference assignment: every living profile receives its
        // immutable reference number at birth (the only creation path is the
        // post-payment living-profile lifecycle).
        static::creating(function (Profile $profile): void {
            if (blank($profile->reference_code)) {
                $profile->reference_code = self::generateUniqueReferenceCode();
            }
        });

        static::updating(function (Profile $profile): void {
            if ($profile->isDirty('reference_code') && filled($profile->getOriginal('reference_code'))) {
                throw new \LogicException('The profile reference number is immutable.');
            }
        });

        static::created(function (Profile $profile): void {
            $profile->ensureMandatoryGeography();
        });
    }

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

    public function ensureMandatoryGeography(): ProfileGeography
    {
        $geography = $this->geography()->firstOrCreate(
            [],
            [
                'country_code' => GeoState::currentCountryCode(),
                'state_region_name' => GeoState::currentDisplayName(),
            ]
        );

        $normalized = ProfileGeography::normalizedStateName($geography->state_region_name);
        if ($geography->state_region_name !== $normalized) {
            $geography->forceFill(['state_region_name' => $normalized])->save();
        }

        return $geography->fresh() ?? $geography;
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

    /** @return HasMany<ProfileExternalLink> */
    public function externalLinks(): HasMany
    {
        return $this->hasMany(ProfileExternalLink::class, 'profile_id')->orderByDesc('id');
    }

    /** @return HasMany<ProfileReaction> */
    public function reactions(): HasMany
    {
        return $this->hasMany(ProfileReaction::class, 'profile_id');
    }

    /** @return HasMany<ProfileContactMessage> */
    public function contactMessages(): HasMany
    {
        return $this->hasMany(ProfileContactMessage::class, 'profile_id');
    }

    /**
     * Customer-facing tier label (Recognised / Acclaimed / Distinguished),
     * resolved from the originating application's package tier.
     */
    public function tierLabel(): ?string
    {
        $tier = $this->application?->package_tier;

        return $tier !== null ? \App\Support\TierLabels::label((string) $tier) : null;
    }

    /**
     * Collision-safe reference allocation. The pre-check keeps collisions rare;
     * the database unique index is the hard guarantee, so the create path
     * fails loudly in the (practically unreachable) race case.
     */
    public static function generateUniqueReferenceCode(): string
    {
        for ($attempt = 0; $attempt < self::MAX_REFERENCE_ATTEMPTS; $attempt++) {
            $candidate = self::REFERENCE_PREFIX.self::randomReferenceSuffix();
            if (! self::query()->where('reference_code', $candidate)->exists()) {
                return $candidate;
            }
        }

        throw new \RuntimeException('Unable to allocate a unique profile reference number.');
    }

    private static function randomReferenceSuffix(): string
    {
        $alphabet = self::REFERENCE_ALPHABET;
        $max = strlen($alphabet) - 1;
        $suffix = '';
        for ($i = 0; $i < self::REFERENCE_LENGTH; $i++) {
            $suffix .= $alphabet[random_int(0, $max)];
        }

        return $suffix;
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
