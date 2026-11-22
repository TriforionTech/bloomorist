<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonthlySummary extends Model
{
    protected $table = 'bl_monthly_summaries_t';

    protected $fillable = [
        'accounting_period_id',
        'net_sales',
        'cogs',
        'gross_profit',
        'operating_expenses',
        'net_income',
        'ending_cash_bank',
        'generated_at',
    ];

    protected $casts = [
        'net_sales' => 'decimal:2',
        'cogs' => 'decimal:2',
        'gross_profit' => 'decimal:2',
        'operating_expenses' => 'decimal:2',
        'net_income' => 'decimal:2',
        'ending_cash_bank' => 'decimal:2',
        'generated_at' => 'datetime',
    ];

    public function accountingPeriod(): BelongsTo
    {
        return $this->belongsTo(AccountingPeriod::class);
    }
}
