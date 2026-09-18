<?php

namespace App\Policies;

use App\Models\InMemoriamProfile;
use App\Models\User;

class InMemoriamProfilePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canViewApplicationQueue();
    }

    public function view(User $user, InMemoriamProfile $inMemoriamProfile): bool
    {
        return $user->canViewApplicationQueue();
    }

    public function create(User $user): bool
    {
        return $user->canManageEditorial();
    }

    public function update(User $user, InMemoriamProfile $inMemoriamProfile): bool
    {
        return $user->canManageEditorial() && ! $inMemoriamProfile->is_sealed;
    }

    public function delete(User $user, InMemoriamProfile $inMemoriamProfile): bool
    {
        return false;
    }

    public function publish(User $user, InMemoriamProfile $inMemoriamProfile): bool
    {
        return $user->isAdmin();
    }

    public function recordOfflinePayment(User $user, InMemoriamProfile $inMemoriamProfile): bool
    {
        return $user->isAdmin();
    }

    public function exceptionalCorrection(User $user, InMemoriamProfile $inMemoriamProfile): bool
    {
        return $user->isAdmin() && $inMemoriamProfile->is_sealed;
    }

    public function manageMedia(User $user, InMemoriamProfile $inMemoriamProfile): bool
    {
        return $user->canManageEditorial() && ! $inMemoriamProfile->is_sealed;
    }

    /**
     * Pending photograph approval may complete after publication (seal does not block review).
     */
    public function approveMedia(User $user, InMemoriamProfile $inMemoriamProfile): bool
    {
        return $user->canManageEditorial();
    }

    public function manageEditorial(User $user, InMemoriamProfile $inMemoriamProfile): bool
    {
        return $user->canManageEditorial() && ! $inMemoriamProfile->is_sealed;
    }
}
