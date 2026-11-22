<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FixedAsset extends Model
{
    protected $table = 'bl_fixed_assets_t';

    protected $fillable = [
        'name',
        'purchase_date',
        'acquisition_cost',
        'useful_life_months',
        'asset_account_id',
        'expense_account_id',
        'accum_dep_account_id',
        'is_active',
    ];

    protected $casts = [
        'purchase_date' => 'date',
        'acquisition_cost' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function depreciations(): HasMany
    {
        return $this->hasMany(FixedAssetDepreciation::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
