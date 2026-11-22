<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountingPeriod extends Model
{
    protected $table = 'bl_accounting_periods_t';

    protected $fillable = [
        'label',
        'start_date',
        'end_date',
        'opening_cash_balance',
        'closing_inventory_value',
        'status',
        'closed_at',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'opening_cash_balance' => 'decimal:2',
        'closing_inventory_value' => 'decimal:2',
        'closed_at' => 'datetime',
    ];

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }

    /**
     * Pastikan hanya ada 1 periode yang berstatus OPEN (jika diperlukan oleh bisnis logika).
     */
    protected static function booted(): void
    {
        static::saving(function (AccountingPeriod $period) {
            if ($period->isDirty('status') && $period->isOpen()) {
                // Opsional: Tutup periode lain yang masih open jika mau dipaksakan 1 open period
                // AccountingPeriod::where('id', '!=', $period->id)
                //     ->where('status', 'open')
                //     ->update(['status' => 'closed', 'closed_at' => now()]);
            }
        });
    }
}
