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

    // ─── Status Helpers ────────────────────────────────────────────

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    /**
     * Invoice hanya bisa di-edit jika masih Pending.
     */
    public function isEditable(): bool
    {
        return $this->isPending();
    }

    /**
     * Invoice hanya bisa di-delete (hard delete) jika masih Pending.
     */
    public function isDeletable(): bool
    {
        return $this->isPending();
    }

    // ─── Server-Side Immutability Guards ───────────────────────────

    protected static function booted(): void
    {
        /**
         * Guard: block updates on non-pending invoices.
         *
         * Exception: jika HANYA field 'status' yang berubah, izinkan
         * karena itu berasal dari InvoiceStatusService yang sudah
         * melakukan validasi state machine sendiri.
         */
        static::updating(function (Invoice $invoice) {
            $dirty = $invoice->getDirty();

            // Jika hanya status yang berubah → transisi via service, izinkan
            if (count($dirty) === 1 && array_key_exists('status', $dirty)) {
                return;
            }

            // Cek status ORIGINAL (sebelum perubahan)
            if ($invoice->getOriginal('status') !== 'pending') {
                throw new \RuntimeException(
                    "Invoice #{$invoice->invoice_number} tidak dapat diedit karena statusnya sudah "
                    . strtoupper($invoice->getOriginal('status')) . '.'
                );
            }
        });

        /**
         * Guard: block deletes on non-pending invoices.
         */
        static::deleting(function (Invoice $invoice) {
            if (! $invoice->isDeletable()) {
                throw new \RuntimeException(
                    "Invoice #{$invoice->invoice_number} tidak dapat dihapus karena statusnya sudah "
                    . strtoupper($invoice->status) . '.'
                );
            }
        });
    }

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
