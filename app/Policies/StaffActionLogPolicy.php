<?php

namespace App\Policies;

use App\Models\StaffActionLog;
use App\Models\User;

class StaffActionLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() && $user->isActiveAccount();
    }

    public function view(User $user, StaffActionLog $log): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, StaffActionLog $log): bool
    {
        return false;
    }

    public function delete(User $user, StaffActionLog $log): bool
    {
        return false;
    }
}
