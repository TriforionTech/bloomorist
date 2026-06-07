<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $table = 'bl_products_t';

    protected $fillable = [
        'nama_barang',
        'harga_beli_barang',
        'harga_jual_barang',
        'stok_barang',
    ];

    public $timestamps = true;

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'product_id');
    }
}
