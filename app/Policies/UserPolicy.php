<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canManageStaffUsers();
    }

    /**
     * Wave 3 separation: the Jannayaks Security Administrator account is
     * INVISIBLE to ordinary staff administration — it cannot be viewed,
     * edited, role-changed, disabled or deleted through any UI path. The
     * account is provisioned, rotated and recovered exclusively through the
     * emergency console command with the recovery key.
     */
    private function touchesSecurityAdmin(User $user, ?User $model): bool
    {
        if ($user->isSecurityAdmin()) {
            return true;
        }

        if ($model !== null && $model->isSecurityAdmin()) {
            return true;
        }

        // Guard the listing filter as well: an admin must not be able to
        // query for the security_admin role.
        if ($model === null && request()->filled('role') && request()->string('role') === User::ROLE_SECURITY_ADMIN) {
            return true;
        }

        return false;
    }

    public function view(User $user, User $model): bool
    {
        if ($this->touchesSecurityAdmin($user, $model)) {
            return false;
        }

        return $user->canManageStaffUsers();
    }

    public function create(User $user): bool
    {
        return $user->canManageStaffUsers();
    }

    public function update(User $user, User $model): bool
    {
        if ($this->touchesSecurityAdmin($user, $model)) {
            return false;
        }

        return $user->canManageStaffUsers();
    }

    public function delete(User $user, User $model): bool
    {
        return false;
    }

    public function restore(User $user, User $model): bool
    {
        return false;
    }

    public function forceDelete(User $user, User $model): bool
    {
        return false;
    }
}
