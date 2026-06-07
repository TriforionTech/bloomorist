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
        if ($invoice->isDirty('status')) {
            $originalStatus = $invoice->getOriginal('status');
            $newStatus = $invoice->status;
            $userId = Filament::auth()->id() ?? 1;

            // Jika status berubah menjadi paid, kurangi stok
            if ($newStatus === 'paid' && $originalStatus !== 'paid') {
                foreach ($invoice->items as $item) {
                    if ($item->product) {
                        $item->product->stok_barang -= $item->quantity;
                        $item->product->save();

                        StockMovement::create([
                            'product_id' => $item->product->id,
                            'type' => 'sale',
                            'quantity' => $item->quantity,
                            'reference_id' => $invoice->invoice_number,
                            'notes' => 'Auto: Invoice paid - ' . $invoice->invoice_number,
                            'user_id' => $userId,
                        ]);
                    }
                }
            }

            // Jika status dari paid berubah menjadi refunded/cancelled, kembalikan stok
            if ($originalStatus === 'paid' && in_array($newStatus, ['refunded', 'cancelled'])) {
                foreach ($invoice->items as $item) {
                    if ($item->product) {
                        $item->product->stok_barang += $item->quantity;
                        $item->product->save();

                        StockMovement::create([
                            'product_id' => $item->product->id,
                            'type' => 'return',
                            'quantity' => $item->quantity,
                            'reference_id' => $invoice->invoice_number,
                            'notes' => 'Auto: Invoice ' . $newStatus . ' - ' . $invoice->invoice_number,
                            'user_id' => $userId,
                        ]);
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
