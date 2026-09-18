<?php

namespace App\Models;

use Database\Factories\InMemoriamProfileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class InMemoriamProfile extends Model
{
    /** @use HasFactory<InMemoriamProfileFactory> */
    use HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PAYMENT_PENDING = 'payment_pending';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_UNDER_EDITORIAL_REVIEW = 'under_editorial_review';

    public const STATUS_COMMISSIONER_REVIEW = 'commissioner_review';

    public const STATUS_PUBLISHED_ARCHIVED = 'published_archived';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_ADMIN_CORRECTION_PENDING = 'admin_correction_pending';

    public const VERIFICATION_UNVERIFIED = 'unverified';

    public const VERIFICATION_VERIFIED = 'verified';

    public const VERIFICATION_COULD_NOT_VERIFY = 'could_not_verify';

    public const VERIFICATION_WAIVED = 'waived';

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
        'slug',
        'slug_generated_at',
        'slug_changed_at',
        'previous_slug',
        'deceased_gender',
        'deceased_date_of_birth',
        'deceased_place_of_birth',
        'deceased_date_of_death',
        'deceased_place_of_death',
        'verification_status',
        'verification_method',
        'verified_at',
        'verification_notes',
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
            'commissioner_display_consent' => 'bool',
            'deceased_date_of_birth' => 'date',
            'deceased_date_of_death' => 'date',
            'verified_at' => 'datetime',
            'commission_amount' => 'decimal:2',
            'commission_gst_amount' => 'decimal:2',
            'commission_paid_at' => 'datetime',
            'submitted_at' => 'datetime',
            'editorial_reviewed_at' => 'datetime',
            'commissioner_approved_at' => 'datetime',
            'published_at' => 'datetime',
            'hosting_starts_on' => 'date',
            'hosting_ends_on' => 'date',
            'renewal_due_on' => 'date',
            'is_sealed' => 'bool',
            'last_admin_corrected_at' => 'datetime',
            'slug_generated_at' => 'datetime',
            'slug_changed_at' => 'datetime',
        ];
    }

    protected static function newFactory(): InMemoriamProfileFactory
    {
        return InMemoriamProfileFactory::new();
    }

    /** @return BelongsTo<User, $this> */
    public function commissionerUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'commissioner_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function lastAdminCorrectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_admin_corrected_by_user_id');
    }

    /** @return HasOne<InMemoriamGeography, $this> */
    public function geography(): HasOne
    {
        return $this->hasOne(InMemoriamGeography::class, 'in_memoriam_profile_id');
    }

    /** @return HasMany<InMemoriamPublicOffice, $this> */
    public function publicOffices(): HasMany
    {
        return $this->hasMany(InMemoriamPublicOffice::class, 'in_memoriam_profile_id')
            ->orderBy('sort_order');
    }

    /** @return HasMany<InMemoriamEditorialContent, $this> */
    public function editorialContents(): HasMany
    {
        return $this->hasMany(InMemoriamEditorialContent::class, 'in_memoriam_profile_id')
            ->orderBy('version_number');
    }

    /** @return MorphMany<MediaItem, $this> */
    public function media(): MorphMany
    {
        return $this->morphMany(MediaItem::class, 'mediable')
            ->orderBy('display_order');
    }

    /** @return MorphMany<MediaItem, $this> */
    public function photographs(): MorphMany
    {
        return $this->media()->where('media_type', MediaItem::TYPE_PROFILE_PHOTO);
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'in_memoriam_profile_id');
    }

    /** @return MorphMany<SlugRedirect, $this> */
    public function slugRedirects(): MorphMany
    {
        return $this->morphMany(SlugRedirect::class, 'redirectable');
    }

    public function displayName(): string
    {
        return filled($this->deceased_display_name)
            ? (string) $this->deceased_display_name
            : (string) $this->deceased_full_name;
    }

    public function isPackagePaid(): bool
    {
        return $this->commission_paid_at !== null;
    }
}
