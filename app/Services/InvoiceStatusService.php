<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;

class InvoiceStatusService
{
    /**
     * Handle status change with DB::transaction wrapper.
     * Semua operasi stok dan (future) accounting di dalam satu transaksi.
     *
     * Rules:
     * - pending → paid    : Potong stok (Sale)
     * - paid → cancelled  : Kembalikan stok (Return)
     * - paid → refunded   : Kembalikan stok (Refund)
     * - pending → cancelled: Tidak ada perubahan stok
     * - cancelled/refunded → paid: Potong stok kembali
     */
    public function changeStatus(Invoice $invoice, string $newStatus): void
    {
        $oldStatus = $invoice->status;

        // Skip jika status tidak berubah
        if ($oldStatus === $newStatus) {
            return;
        }

        DB::transaction(function () use ($invoice, $oldStatus, $newStatus) {
            // 1. Process stock mutations
            $this->processStockMutation($invoice, $oldStatus, $newStatus);

            // 2. Generate accounting journal (placeholder)
            $this->generateAccountingJournal($invoice, $oldStatus, $newStatus);

            // 3. Update status
            $invoice->update(['status' => $newStatus]);
        });
    }

    /**
     * Process stock mutation berdasarkan perubahan status invoice.
     * Mencatat histori keluar/masuk barang (Kartu Stok) agar sinkron dengan Buku Besar.
     */
    protected function processStockMutation(Invoice $invoice, string $oldStatus, string $newStatus): void
    {
        $items = $invoice->items()->with('product')->get();
        $userId = \Filament\Facades\Filament::auth()->id() ?? 1;

        // Tentukan apakah perlu potong atau kembalikan stok
        $shouldDecrementStock = false;
        $shouldIncrementStock = false;

        // Dari status non-paid → paid: potong stok
        if ($newStatus === 'paid' && $oldStatus !== 'paid') {
            $shouldDecrementStock = true;
        }

        // Dari paid → cancelled/refunded: kembalikan stok
        if ($oldStatus === 'paid' && in_array($newStatus, ['cancelled', 'refunded'])) {
            $shouldIncrementStock = true;
        }

        if ($shouldDecrementStock) {
            foreach ($items as $item) {
                if ($item->product) {
                    $item->product->decrement('stok', $item->quantity);

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

        if ($shouldIncrementStock) {
            foreach ($items as $item) {
                if ($item->product) {
                    $item->product->increment('stok', $item->quantity);

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

    /**
     * Placeholder: Generate Jurnal Umum otomatis.
     * 
     * TODO (Sprint berikutnya):
     * - paid    → Pengakuan Piutang/Kas, Penjualan, dan HPP
     * - refunded → Reverse entries (Retur Penjualan)
     * 
     * @param Invoice $invoice   Invoice yang statusnya berubah
     * @param string  $oldStatus Status lama
     * @param string  $newStatus Status baru
     */
    protected function generateAccountingJournal(Invoice $invoice, string $oldStatus, string $newStatus): void
    {
        // TODO: Implement accounting journal generation
        // 
        // Contoh implementasi:
        // if ($newStatus === 'paid') {
        //     JournalEntry::create([
        //         'date'        => now(),
        //         'description' => "Penjualan Invoice #{$invoice->invoice_number}",
        //         'entries'     => [
        //             ['account' => 'Piutang Usaha',  'debit' => $invoice->grand_total, 'credit' => 0],
        //             ['account' => 'Penjualan',      'debit' => 0, 'credit' => $invoice->grand_total],
        //         ],
        //     ]);
        // }
        //
        // if ($newStatus === 'refunded' && $oldStatus === 'paid') {
        //     // Reverse the journal entry
        // }
    }
}
