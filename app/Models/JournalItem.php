<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JournalItem extends Model
{
    protected $table = 'bl_journal_items_t';

    protected $fillable = [
        'journal_id',
        'coa_id',
        'kode_coa',
        'debit',
        'kredit',
    ];

    protected $casts = [
        'debit'  => 'integer',
        'kredit' => 'integer',
    ];

    /**
     * The journal header this item belongs to.
     */
    public function journal(): BelongsTo
    {
        return $this->belongsTo(GeneralJournal::class, 'journal_id');
    }

    /**
     * The chart of account this item references.
     */
    public function coa(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'coa_id');
    }
}
