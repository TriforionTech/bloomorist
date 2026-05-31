<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    protected $table = 'bl_invoices_t';

    protected $fillable = [
        'invoice_number',
        'customer_type',
        'customer_id',
        'discount_mode',
        'discount_mode_member',
        'custom_discount',
        'ongkir',
        'use_box',
        'use_wrapping',
        'subtotal',
        'discount_total',
        'grand_total',
        'issued_date',
        'due_date',
        'status',
    ];

    protected $casts = [
        'use_box' => 'boolean',
        'use_wrapping' => 'boolean',
        'discount_mode_member' => 'boolean',
        'issued_date' => 'date',
        'due_date' => 'date',
    ];

    public function items()
    {
        return $this->hasMany(InvoiceItem::class, 'invoice_id');
    }

    public function regularItems()
    {
        return $this->hasMany(InvoiceItem::class, 'invoice_id')->whereHas('product', function ($q) {
            $q->whereNotIn('nama_barang', ['Box', 'Wrapping']);
        });
    }

    public function boxItem()
    {
        return $this->hasOne(InvoiceItem::class, 'invoice_id')->whereHas('product', function ($q) {
            $q->where('nama_barang', 'Box');
        });
    }

    public function wrappingItem()
    {
        return $this->hasOne(InvoiceItem::class, 'invoice_id')->whereHas('product', function ($q) {
            $q->where('nama_barang', 'Wrapping');
        });
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function membership()
    {
        return $this->belongsTo(Membership::class, 'membership_id');
    }
}
