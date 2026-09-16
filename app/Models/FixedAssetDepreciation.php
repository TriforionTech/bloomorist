<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FixedAssetDepreciation extends Model
{
    protected $table = 'bl_fixed_asset_depreciations_t';

    protected $fillable = [
        'fixed_asset_id',
        'accounting_period_id',
        'monthly_depreciation',
        'accumulated_depreciation',
        'net_book_value',
        'journal_entry_id',
    ];

    protected $casts = [
        'monthly_depreciation' => 'decimal:2',
        'accumulated_depreciation' => 'decimal:2',
        'net_book_value' => 'decimal:2',
    ];

    public function fixedAsset(): BelongsTo
    {
        return $this->belongsTo(FixedAsset::class);
    }

    public function accountingPeriod(): BelongsTo
    {
        return $this->belongsTo(AccountingPeriod::class);
    }
}
