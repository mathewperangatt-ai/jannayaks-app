<?php

namespace App\Policies;

use App\Models\EditorialCustomerApproval;
use App\Models\User;

class EditorialCustomerApprovalPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() && $user->isActiveAccount();
    }

    public function view(User $user, EditorialCustomerApproval $approval): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, EditorialCustomerApproval $approval): bool
    {
        return false;
    }

    public function delete(User $user, EditorialCustomerApproval $approval): bool
    {
        return false;
    }
}
