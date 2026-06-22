<?php

namespace App\Models;

use App\Services\InvoiceStatusService;
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

    /**
     * Regular items: exclude Box dan Wrapping berdasarkan snapshot_name.
     * Menggunakan snapshot agar tetap berfungsi meski produk di-deactivate/dihapus.
     */
    public function regularItems()
    {
        return $this->hasMany(InvoiceItem::class, 'invoice_id')
            ->whereNotIn('snapshot_name', ['Box', 'Wrapping']);
    }

    /**
     * Box item berdasarkan snapshot_name.
     */
    public function boxItem()
    {
        return $this->hasOne(InvoiceItem::class, 'invoice_id')
            ->where('snapshot_name', 'Box');
    }

    /**
     * Wrapping item berdasarkan snapshot_name.
     */
    public function wrappingItem()
    {
        return $this->hasOne(InvoiceItem::class, 'invoice_id')
            ->where('snapshot_name', 'Wrapping');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    /**
     * Static proxy: delegasi ke InvoiceStatusService.
     * Dipertahankan agar semua caller (InvoicesTable, dll) tidak perlu diubah.
     */
    public static function handleStatusChange(self $invoice, string $newStatus): void
    {
        app(InvoiceStatusService::class)->changeStatus($invoice, $newStatus);
    }
}
