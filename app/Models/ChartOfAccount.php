<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChartOfAccount extends Model
{
    protected $table = 'bl_coa_t';

    protected $fillable = [
        'kode_akun',
        'nama_akun',
        'kategori',
        'saldo_normal',
    ];

    /**
     * All journal line items referencing this account.
     */
    public function journalItems(): HasMany
    {
        return $this->hasMany(JournalItem::class, 'coa_id');
    }

    /**
     * Expenses charged to this account (as Beban).
     */
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class, 'coa_id');
    }

    /**
     * Check if this is a debit-normal account.
     */
    public function isDebitNormal(): bool
    {
        return $this->saldo_normal === 'Debit';
    }
}
