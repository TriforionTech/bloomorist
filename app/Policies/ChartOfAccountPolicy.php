<?php

namespace App\Policies;

use App\Models\ChartOfAccount;
use App\Models\User;

class ChartOfAccountPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_super_admin;
    }

    public function view(User $user, ChartOfAccount $chartOfAccount): bool
    {
        return $user->is_super_admin;
    }

    public function create(User $user): bool
    {
        return $user->is_super_admin;
    }

    public function update(User $user, ChartOfAccount $chartOfAccount): bool
    {
        return $user->is_super_admin;
    }

    public function delete(User $user, ChartOfAccount $chartOfAccount): bool
    {
        return $user->is_super_admin;
    }

    public function deleteAny(User $user): bool
    {
        return $user->is_super_admin;
    }
}
