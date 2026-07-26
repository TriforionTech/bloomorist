<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    protected $table = 'bl_expenses_t';

    protected $fillable = [
        'keterangan',
        'nominal',
        'coa_id',
        'coa_kredit_id',
        'status',
    ];

    protected $casts = [
        'nominal' => 'integer',
    ];

    // ─── Status Helpers ────────────────────────────────────────────

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isPosted(): bool
    {
        return $this->status === 'posted';
    }

    public function isVoid(): bool
    {
        return $this->status === 'void';
    }

    /**
     * Expense hanya bisa di-edit jika masih Draft.
     */
    public function isEditable(): bool
    {
        return $this->isDraft();
    }

    /**
     * Expense hanya bisa di-delete (hard delete) jika masih Draft.
     */
    public function isDeletable(): bool
    {
        return $this->isDraft();
    }

    // ─── Server-Side Immutability Guards ───────────────────────────

    protected static function booted(): void
    {
        /**
         * Guard: block updates on non-draft expenses.
         *
         * Exception: jika HANYA field 'status' yang berubah, izinkan
         * karena itu berasal dari ExpenseService yang sudah
         * melakukan validasi state machine sendiri.
         */
        static::updating(function (Expense $expense) {
            $dirty = $expense->getDirty();

            // Jika hanya status yang berubah → transisi via service, izinkan
            if (count($dirty) === 1 && array_key_exists('status', $dirty)) {
                return;
            }

            // Cek status ORIGINAL (sebelum perubahan)
            if ($expense->getOriginal('status') !== 'draft') {
                throw new \RuntimeException(
                    "Expense '{$expense->keterangan}' tidak dapat diedit karena statusnya sudah "
                    . strtoupper($expense->getOriginal('status')) . '.'
                );
            }
        });

        /**
         * Guard: block deletes on non-draft expenses.
         */
        static::deleting(function (Expense $expense) {
            if (! $expense->isDeletable()) {
                throw new \RuntimeException(
                    "Expense '{$expense->keterangan}' tidak dapat dihapus karena statusnya sudah "
                    . strtoupper($expense->status) . '.'
                );
            }
        });
    }

    // ─── Relationships ────────────────────────────────────────────

    /**
     * The expense account (Beban) — debit side.
     */
    public function coaBeban(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'coa_id');
    }

    /**
     * The cash/bank account — credit side (user-selected).
     */
    public function coaKredit(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'coa_kredit_id');
    }
}
