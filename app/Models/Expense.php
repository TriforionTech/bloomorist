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
    ];

    protected $casts = [
        'nominal' => 'integer',
    ];

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
