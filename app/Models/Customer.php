<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use SoftDeletes;

    protected $table = 'bl_customers_t';

    protected $fillable = [
        'nama',
        'tipe_customer',
        'alias',
        'email',
        'alamat',
        'kota',
        'provinsi',
        'negara',
        'nomor_hp',
        'membership_id',
    ];

    public $timestamps = true;

    public function membership(): BelongsTo
    {
        return $this->belongsTo(Membership::class, 'membership_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'customer_id');
    }
}
