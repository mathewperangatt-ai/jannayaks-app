<?php

namespace App\Policies;

use App\Models\SourceMaterial;
use App\Models\User;

class SourceMaterialPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canAccessSourceMaterial();
    }

    public function view(User $user, SourceMaterial $sourceMaterial): bool
    {
        return $user->canAccessSourceMaterial();
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, SourceMaterial $sourceMaterial): bool
    {
        return $user->canManageEditorial();
    }

    public function delete(User $user, SourceMaterial $sourceMaterial): bool
    {
        return false;
    }

    public function download(User $user, SourceMaterial $sourceMaterial): bool
    {
        if ($sourceMaterial->isPurged()) {
            return false;
        }

        return $user->canAccessSourceMaterial();
    }

    public function restore(User $user, SourceMaterial $sourceMaterial): bool
    {
        return false;
    }

    public function forceDelete(User $user, SourceMaterial $sourceMaterial): bool
    {
        return false;
    }
}
