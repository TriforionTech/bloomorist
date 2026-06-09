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
