<?php

namespace App\Services;

use App\Models\Expense;
use Illuminate\Support\Facades\DB;

class ExpenseService
{
    /**
     * Post a draft expense: create journal entries and mark as posted.
     *
     * - Validates status === draft
     * - Idempotency check (skip if journal already exists)
     * - Creates journal via AccountingService
     * - Updates status → posted
     * - All within DB::transaction
     */
    public function postExpense(Expense $expense): void
    {
        if (! $expense->isDraft()) {
            throw new \RuntimeException(
                "Expense '{$expense->keterangan}' tidak dapat di-post karena statusnya sudah "
                . strtoupper($expense->status) . '.'
            );
        }

        DB::transaction(function () use ($expense) {
            // Lock record to prevent race condition
            $expense = Expense::lockForUpdate()->findOrFail($expense->id);

            // Re-validate after lock
            if (! $expense->isDraft()) {
                throw new \RuntimeException(
                    "Expense '{$expense->keterangan}' sudah diproses (race condition). Silakan refresh halaman."
                );
            }

            // Create journal (idempotent — AccountingService checks for existing)
            app(AccountingService::class)->createExpenseJournal($expense);

            // Update status
            $expense->update(['status' => 'posted']);
        });
    }

    /**
     * Void a posted expense: create reversing journal and mark as void.
     *
     * - Validates status === posted
     * - DOES NOT delete original journal
     * - Creates reversing journal entry (debit/kredit swapped)
     * - Updates status → void
     * - All within DB::transaction
     */
    public function voidExpense(Expense $expense): void
    {
        if (! $expense->isPosted()) {
            throw new \RuntimeException(
                "Expense '{$expense->keterangan}' tidak dapat di-void karena statusnya "
                . strtoupper($expense->status) . '.'
            );
        }

        DB::transaction(function () use ($expense) {
            // Lock record to prevent race condition
            $expense = Expense::lockForUpdate()->findOrFail($expense->id);

            // Re-validate after lock
            if (! $expense->isPosted()) {
                throw new \RuntimeException(
                    "Expense '{$expense->keterangan}' sudah diproses (race condition). Silakan refresh halaman."
                );
            }

            // Create reversing journal (does NOT delete original)
            app(AccountingService::class)->createExpenseReversalJournal($expense);

            // Update status
            $expense->update(['status' => 'void']);
        });
    }
}
