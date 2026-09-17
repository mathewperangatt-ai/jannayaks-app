<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable([
    'name',
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

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'mobile_verified_at' => 'datetime',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->role === self::ROLE_ADMIN
            && ($this->account_status ?? 'active') === 'active';
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isStaff(): bool
    {
        return in_array($this->role, [self::ROLE_ADMIN, self::ROLE_EDITOR, self::ROLE_SUPPORT], true);
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

    /** @return HasMany<Membership> */
    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class, 'user_id');
    }
}
