<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    protected $table = 'bl_customers_t';

    protected $fillable = [
        'nama',
        'alias',
        'email',
        'alamat',
        'kota',
        'provinsi',
        'negara',
        'nomor_hp',
        'membership_id'
    ];

    public $timestamps = true;
}
