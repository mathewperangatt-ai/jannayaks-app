<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable([
    'name',
    'username',
    'email',
    'email_verified_at',
    'password',
    'google_id',
    'mobile',
    'mobile_verified_at',
    'account_status',
    'role',
    'remember_token',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_MEMBER = 'member';

    public const ROLE_ADMIN = 'admin';

    public const ROLE_EDITOR = 'editor';

    public const ROLE_SUPPORT = 'support';

    /**
     * Jannayaks Security Administrator — the ONLY role authorised to modify
     * published profile content, approve user-submitted corrections and
     * photograph replacements, resolve integrity incidents and restore
     * suspended profiles (subject to the integrity safeguards). Deliberately
     * NOT an unrestricted super-admin: no finance, staff-management,
     * infrastructure or security-configuration authority.
     */
    public const ROLE_SECURITY_ADMIN = 'security_admin';

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'mobile_verified_at' => 'datetime',
            'totp_secret' => 'encrypted',
            'totp_recovery_codes' => 'encrypted:array',
            'totp_enabled_at' => 'datetime',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->isStaff() && $this->isActiveAccount();
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isEditor(): bool
    {
        return $this->role === self::ROLE_EDITOR;
    }

    public function isSupport(): bool
    {
        return $this->role === self::ROLE_SUPPORT;
    }

    public function isSecurityAdmin(): bool
    {
        return $this->role === self::ROLE_SECURITY_ADMIN;
    }

    public function isStaff(): bool
    {
        return in_array($this->role, [self::ROLE_ADMIN, self::ROLE_EDITOR, self::ROLE_SUPPORT, self::ROLE_SECURITY_ADMIN], true);
    }

    /**
     * Exclusive authority over already-published profile administration:
     * published EN/ML content, photograph replacement, correction approvals,
     * integrity incident handling and visibility restoration.
     *
     * Transitional window: while JANNAYAKS_ALLOW_LEGACY_ADMIN_PROFILE_EDITS
     * is true, the original admin role retains these rights until the
     * Security Administrator account is provisioned and verified. Flip the
     * flag to false to enforce strict separation.
     */
    public function canManagePublishedProfiles(): bool
    {
        if (! $this->isActiveAccount()) {
            return false;
        }

        if ($this->isSecurityAdmin()) {
            return true;
        }

        return $this->isAdmin()
            && (bool) config('jannayaks.security.integrity.published_profile_admin.allow_legacy_admin', true);
    }

    public function isActiveAccount(): bool
    {
        return ($this->account_status ?? 'active') === 'active';
    }

    /** Financial records, invoices, refunds, mark-paid / waive UI. */
    public function canManageFinance(): bool
    {
        return $this->isAdmin() && $this->isActiveAccount();
    }

    /** Assign staff roles and manage user accounts. */
    public function canManageStaffUsers(): bool
    {
        return $this->isAdmin() && $this->isActiveAccount();
    }

    /** Editorial workspace + application source material (not finance). */
    public function canManageEditorial(): bool
    {
        return $this->isActiveAccount()
            && in_array($this->role, [self::ROLE_ADMIN, self::ROLE_EDITOR], true);
    }

    /** Read application operating queues (includes support). */
    public function canViewApplicationQueue(): bool
    {
        return $this->isStaff() && $this->isActiveAccount();
    }

    /**
     * Internal account email (users.email) for operational staff communication.
     * Distinct from preferred/public contact fields and private mobile.
     */
    public function canViewApplicantAccountEmail(): bool
    {
        return $this->canViewApplicationQueue();
    }

    /**
     * Preferred contact email/mobile and other private contact fields.
     * Not the same as internal account email.
     */
    public function canViewApplicantContactDetails(): bool
    {
        return $this->isAdmin() && $this->isActiveAccount();
    }

    /** Interview answers and private source uploads. */
    public function canAccessSourceMaterial(): bool
    {
        return $this->canManageEditorial();
    }

    /** @return HasOne<Profile> */
    public function profile(): HasOne
    {
        return $this->hasOne(Profile::class, 'user_id');
    }

    /** @return HasMany<ConsentRecord> */
    public function consents(): HasMany
    {
        return $this->hasMany(ConsentRecord::class, 'user_id');
    }

    /** @return HasMany<ProfileVerification> */
    public function verificationsPerformed(): HasMany
    {
        return $this->hasMany(ProfileVerification::class, 'verifier_user_id');
    }

    /** @return HasMany<EditorialContent> */
    public function editorialCreated(): HasMany
    {
        return $this->hasMany(EditorialContent::class, 'created_by_id');
    }

    /** @return HasMany<EditorialContent> */
    public function editorialReviewed(): HasMany
    {
        return $this->hasMany(EditorialContent::class, 'reviewed_by_id');
    }

    /** @return HasMany<MediaItem> */
    public function uploads(): HasMany
    {
        return $this->hasMany(MediaItem::class, 'uploaded_by_id');
    }

    /** @return HasMany<Application> */
    public function applications(): HasMany
    {
        return $this->hasMany(Application::class, 'user_id');
    }

    /** @return HasMany<Application> */
    public function waivedApplications(): HasMany
    {
        return $this->hasMany(Application::class, 'waived_by_user_id');
    }

    /** @return HasManyThrough<Membership, Profile> */
    public function memberships(): HasManyThrough
    {
        return $this->hasManyThrough(Membership::class, Profile::class, 'user_id', 'profile_id');
    }
}
