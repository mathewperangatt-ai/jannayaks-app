<?php

namespace App\Policies;

use App\Models\EditorialContent;
use App\Models\User;

class EditorialContentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canManageEditorial();
    }

    public function view(User $user, EditorialContent $editorialContent): bool
    {
        return $user->canManageEditorial();
    }

    public function create(User $user): bool
    {
        return $user->canManageEditorial();
    }

    public function update(User $user, EditorialContent $editorialContent): bool
    {
        return $user->canManageEditorial();
    }

    public function delete(User $user, EditorialContent $editorialContent): bool
    {
        return $user->isAdmin() && $user->isActiveAccount();
    }

    public function restore(User $user, EditorialContent $editorialContent): bool
    {
        return false;
    }

    public function forceDelete(User $user, EditorialContent $editorialContent): bool
    {
        return false;
    }
}
