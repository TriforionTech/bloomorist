<?php

namespace App\Observers;

use App\Models\Invoice;

class InvoiceObserver
{
    /**
     * Handle the Invoice "saved" event.
     */
    public function saved(Invoice $invoice): void
    {
        $subtotal = 0;
        foreach ($invoice->items as $item) {
            $subtotal += $item->discount_price;
        }

        $ongkir = (float) $invoice->ongkir;
        $box_fee = (float) $invoice->box_fee;
        $wrapping_fee = (float) $invoice->wrapping_fee;

        $grand_total = $subtotal + $ongkir + $box_fee + $wrapping_fee;

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
        if ($invoice->isDirty('status')) {
            $originalStatus = $invoice->getOriginal('status');
            $newStatus = $invoice->status;

            // Jika status berubah menjadi paid, kurangi stok
            if ($newStatus === 'paid' && $originalStatus !== 'paid') {
                foreach ($invoice->items as $item) {
                    if ($item->product) {
                        $item->product->stok_barang -= $item->quantity;
                        $item->product->save();
                    }
                }
            }

            // Jika status dari paid berubah menjadi refunded/cancelled, kembalikan stok
            if ($originalStatus === 'paid' && in_array($newStatus, ['refunded', 'cancelled'])) {
                foreach ($invoice->items as $item) {
                    if ($item->product) {
                        $item->product->stok_barang += $item->quantity;
                        $item->product->save();
                    }
                }
            }
        }
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
