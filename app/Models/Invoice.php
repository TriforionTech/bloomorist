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

    /**
     * Handle status change with automatic stock adjustment.
     * ALL items (including Box/Wrapping) affect stock.
     *
     * Rules:
     * - pending → paid    : Potong stok (Sale)
     * - paid → cancelled  : Kembalikan stok (Return)
     * - paid → refunded   : Kembalikan stok (Refund)
     * - pending → cancelled: Tidak ada perubahan stok
     * - cancelled/refunded → paid: Potong stok kembali
     */
    public static function handleStatusChange(self $invoice, string $newStatus): void
    {
        $oldStatus = $invoice->status;

        // Skip jika status tidak berubah
        if ($oldStatus === $newStatus) {
            return;
        }

        $items = $invoice->items()->with('product')->get();

        // Tentukan apakah perlu potong atau kembalikan stok
        $shouldDecrementStock = false;
        $shouldIncrementStock = false;

        // Dari status non-paid → paid: potong stok
        if ($newStatus === 'paid' && $oldStatus !== 'paid') {
            // Kecuali dari pending ke cancel (tidak potong)
            $shouldDecrementStock = true;
        }

        // Dari paid → cancelled/refunded: kembalikan stok
        if ($oldStatus === 'paid' && in_array($newStatus, ['cancelled', 'refunded'])) {
            $shouldIncrementStock = true;
        }

        if ($shouldDecrementStock) {
            foreach ($items as $item) {
                if ($item->product) {
                    $item->product->decrement('stok_barang', $item->quantity);
                }
            }
        }

        if ($shouldIncrementStock) {
            foreach ($items as $item) {
                if ($item->product) {
                    $item->product->increment('stok_barang', $item->quantity);
                }
            }
        }

        // Update status
        $invoice->update(['status' => $newStatus]);
    }
}
