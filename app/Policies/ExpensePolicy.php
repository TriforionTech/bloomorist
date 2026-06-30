<?php

namespace App\Policies;

use App\Models\Expense;
use App\Models\User;

class ExpensePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_super_admin;
    }

    public function view(User $user, Expense $expense): bool
    {
        return $user->is_super_admin;
    }

    public function create(User $user): bool
    {
        return $user->is_super_admin;
    }

    public function update(User $user, Expense $expense): bool
    {
        return $user->is_super_admin;
    }

    public function delete(User $user, Expense $expense): bool
    {
        return $user->is_super_admin;
    }

    public function deleteAny(User $user): bool
    {
        return $user->is_super_admin;
    }
}
