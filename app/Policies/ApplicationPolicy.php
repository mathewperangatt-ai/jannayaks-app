<?php

namespace App\Policies;

use App\Models\Application;
use App\Models\User;

class ApplicationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canViewApplicationQueue();
    }

    public function view(User $user, Application $application): bool
    {
        return $user->canViewApplicationQueue();
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Application $application): bool
    {
        return $user->canManageEditorial();
    }

    public function delete(User $user, Application $application): bool
    {
        return false;
    }

    public function restore(User $user, Application $application): bool
    {
        return false;
    }

    public function forceDelete(User $user, Application $application): bool
    {
        return false;
    }

    public function viewAccountEmail(User $user, Application $application): bool
    {
        return $user->canViewApplicantAccountEmail();
    }

    public function viewContactDetails(User $user, Application $application): bool
    {
        return $user->canViewApplicantContactDetails();
    }

    public function viewFinancialSummary(User $user, Application $application): bool
    {
        return $user->canManageFinance();
    }

    public function changeWorkflowStatus(User $user, Application $application): bool
    {
        return $user->canManageEditorial();
    }
}
