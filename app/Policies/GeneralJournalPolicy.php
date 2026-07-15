<?php

namespace App\Policies;

use App\Models\GeneralJournal;
use App\Models\User;

class GeneralJournalPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_super_admin;
    }

    public function view(User $user, GeneralJournal $generalJournal): bool
    {
        return $user->is_super_admin;
    }

    public function create(User $user): bool
    {
        return $user->is_super_admin;
    }

    /**
     * Journals are immutable. No edits allowed.
     * Errors must be corrected via reversal journals.
     */
    public function update(User $user, GeneralJournal $generalJournal): bool
    {
        return false;
    }

    /**
     * Journals are immutable. No deletes allowed.
     * Errors must be corrected via reversal journals.
     */
    public function delete(User $user, GeneralJournal $generalJournal): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
