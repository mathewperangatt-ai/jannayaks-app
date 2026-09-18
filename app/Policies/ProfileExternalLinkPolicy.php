<?php

namespace App\Policies;

use App\Models\ProfileExternalLink;
use App\Models\User;

class ProfileExternalLinkPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canViewApplicationQueue();
    }

    public function view(User $user, ProfileExternalLink $link): bool
    {
        if ($user->canViewApplicationQueue()) {
            return true;
        }

        return (int) $link->profile?->user_id === (int) $user->id;
    }

    public function create(User $user): bool
    {
        return $user->role === User::ROLE_MEMBER || $user->canManageEditorial();
    }

    public function update(User $user, ProfileExternalLink $link): bool
    {
        return (int) $link->profile?->user_id === (int) $user->id;
    }

    public function review(User $user, ProfileExternalLink $link): bool
    {
        return $user->canManageEditorial();
    }

    public function delete(User $user, ProfileExternalLink $link): bool
    {
        return $user->isAdmin();
    }
}
