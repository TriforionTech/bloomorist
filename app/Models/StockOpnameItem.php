<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockOpnameItem extends Model
{
    use HasFactory;

    protected $table = 'bl_stock_opname_items_t';

    protected $fillable = [
        'stock_opname_id',
        'product_id',
        'system_qty',
        'physical_qty',
        'variance',
        'unit_cost',
        'total_value',
        'notes',
    ];

    public function stockOpname()
    {
        return $this->belongsTo(StockOpname::class, 'stock_opname_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    protected static function booted(): void
    {
        static::saving(function (StockOpnameItem $item) {
            if ($item->product_id) {
                if ($item->system_qty === null || $item->unit_cost === null) {
                    $product = $item->product ?? Product::find($item->product_id);
                    if ($product) {
                        $item->system_qty = $item->system_qty ?? $product->stok;
                        $item->unit_cost = $item->unit_cost ?? $product->harga_beli;
                    }
                }
            }

            if ($item->system_qty !== null && $item->physical_qty !== null) {
                $item->variance = $item->physical_qty - $item->system_qty;
                $item->total_value = $item->variance * ($item->unit_cost ?? 0);
            }
        });
    }
}
