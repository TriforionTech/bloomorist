<?php

namespace App\Observers;

use App\Models\Invoice;
use App\Models\StockMovement;
use Filament\Facades\Filament;

class InvoiceObserver
{
    /**
     * Handle the Invoice "saved" event.
     */
    public function saved(Invoice $invoice): void
    {
        // Reload items to get fresh data
        $invoice->load('items');

        // Subtotal = hanya produk reguler (tanpa Box & Wrapping)
        $subtotal = $invoice->items
            ->whereNotIn('snapshot_name', ['Box', 'Wrapping'])
            ->sum('discount_price');

        // Grand total = semua items (termasuk Box & Wrapping) + ongkir
        $allItemsTotal = $invoice->items->sum('discount_price');
        $ongkir = (float) $invoice->ongkir;
        $grand_total = $allItemsTotal + $ongkir;

        if ($invoice->subtotal != $subtotal || $invoice->grand_total != $grand_total) {
            $invoice->subtotal = $subtotal;
            $invoice->grand_total = $grand_total;
            $invoice->saveQuietly();
        }
    }

    /**
     * Handle the Invoice "created" event.
     */
    public function created(Invoice $invoice): void
    {
        //
    }

    /**
     * Handle the Invoice "updated" event.          
     */
    public function updated(Invoice $invoice): void
    {
        //
    }

    /**
     * Handle the Invoice "deleting" event.
     *
     * Invoice non-pending sekarang diblokir oleh model boot guard.
     * Observer tidak perlu lagi memproses status change saat delete.
     */
    public function deleting(Invoice $invoice): void
    {
        //
    }

    /**
     * Handle the Invoice "deleted" event.
     */
    public function deleted(Invoice $invoice): void
    {
        //
    }

    /**
     * Handle the Invoice "restored" event.
     */
    public function restored(Invoice $invoice): void
    {
        //
    }

    /**
     * Handle the Invoice "force deleted" event.
     */
    public function forceDeleted(Invoice $invoice): void
    {
        //
    }
}
