<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountingAudit extends Model
{
    protected $table = 'bl_accounting_audits_t';

    protected $fillable = [
        'user_id',
        'journal_id',
        'action',
        'old_values',
        'new_values',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function journal(): BelongsTo
    {
        return $this->belongsTo(GeneralJournal::class, 'journal_id');
    }

    public function getDisplayNoBuktiAttribute(): string
    {
        return $this->journal?->no_bukti
            ?? $this->old_values['no_bukti']
            ?? $this->new_values['header']['no_bukti']
            ?? $this->new_values['no_bukti']
            ?? '-';
    }

    public function getDisplayKeteranganAttribute(): string
    {
        return $this->journal?->keterangan
            ?? $this->old_values['keterangan']
            ?? $this->new_values['header']['keterangan']
            ?? $this->new_values['keterangan']
            ?? '-';
    }
}
