<?php

namespace App\Policies;

use App\Models\MediaItem;
use App\Models\Profile;
use App\Models\User;

class MediaItemPolicy
{
    public function view(User $user, MediaItem $mediaItem): bool
    {
        return $this->ownsMediableProfile($user, $mediaItem) || $user->canManageEditorial();
    }

    public function create(User $user): bool
    {
        return $user->role === User::ROLE_MEMBER || $user->canManageEditorial();
    }

    public function update(User $user, MediaItem $mediaItem): bool
    {
        return $this->ownsMediableProfile($user, $mediaItem) || $user->canManageEditorial();
    }

    public function delete(User $user, MediaItem $mediaItem): bool
    {
        return $this->ownsMediableProfile($user, $mediaItem) || $user->canManageEditorial();
    }

    public function setPrimary(User $user, MediaItem $mediaItem): bool
    {
        return $this->update($user, $mediaItem);
    }

    private function ownsMediableProfile(User $user, MediaItem $mediaItem): bool
    {
        $owner = $mediaItem->mediable;
        if (! $owner instanceof Profile) {
            return false;
        }

        return (int) $owner->user_id === (int) $user->id;
    }
}
