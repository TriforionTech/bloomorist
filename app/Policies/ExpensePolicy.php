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

    /**
     * Expense hanya boleh di-update jika status Draft DAN user is super_admin.
     */
    public function update(User $user, Expense $expense): bool
    {
        return $user->is_super_admin && $expense->isEditable();
    }

    /**
     * Expense hanya boleh di-delete jika status Draft DAN user is super_admin.
     */
    public function delete(User $user, Expense $expense): bool
    {
        return $user->is_super_admin && $expense->isDeletable();
    }

    public function deleteAny(User $user): bool
    {
        return $user->is_super_admin;
    }
}
