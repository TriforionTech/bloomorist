<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $table = 'bl_products_t';

    protected $fillable = [
        'nama_barang',
        // 'deskripsi',
        'harga_beli_barang',
        'harga_jual_barang',
    ];

    public $timestamps = true;
}
