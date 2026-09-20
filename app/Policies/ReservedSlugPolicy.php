<?php

namespace App\Policies;

use App\Models\ReservedSlug;
use App\Models\User;

class ReservedSlugPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() && $user->isActiveAccount();
    }

    public function view(User $user, ReservedSlug $reservedSlug): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, ReservedSlug $reservedSlug): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, ReservedSlug $reservedSlug): bool
    {
        return $this->viewAny($user);
    }

    public function restore(User $user, ReservedSlug $reservedSlug): bool
    {
        return false;
    }

    public function forceDelete(User $user, ReservedSlug $reservedSlug): bool
    {
        return false;
    }
}
