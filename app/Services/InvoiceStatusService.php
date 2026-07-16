<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;

class InvoiceStatusService
{
    /**
     * Handle status change with DB::transaction wrapper.
     * Semua operasi stok dan accounting di dalam satu transaksi.
     *
     * Rules:
     * - pending → paid    : Potong stok (Sale) + Journal (Debit Kas, Kredit Pendapatan)
     * - paid → cancelled  : Kembalikan stok (Return) + Reversal Journal
     * - paid → pending    : Kembalikan stok (Return) + Reversal Journal
     * - pending → cancelled: Tidak ada perubahan stok, tidak ada journal
     * - cancelled → paid  : Potong stok kembali + Journal
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

            // 2. Generate accounting journal
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

        // Dari paid → any other status (pending/cancelled): kembalikan stok
        if ($oldStatus === 'paid' && $newStatus !== 'paid') {
            $shouldIncrementStock = true;
        }

        if ($shouldDecrementStock) {
            foreach ($items as $item) {
                if ($item->product) {
                    if ($item->product->stok < $item->quantity) {
                        throw new \Exception("Stok tidak mencukupi untuk produk: {$item->product->nama}. Stok saat ini: {$item->product->stok}, dibutuhkan: {$item->quantity}.");
                    }

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
     * Generate Jurnal Umum otomatis berdasarkan perubahan status invoice.
     *
     * - non-paid → paid: Debit Kas & Bank, Kredit Pendapatan Penjualan
     * - paid → cancelled/refunded: Reversal (Debit Pendapatan, Kredit Kas)
     */
    protected function generateAccountingJournal(Invoice $invoice, string $oldStatus, string $newStatus): void
    {
        $accountingService = app(AccountingService::class);

        // Invoice becomes PAID → create revenue journal
        if ($newStatus === 'paid' && $oldStatus !== 'paid') {
            $accountingService->createInvoicePaidJournal($invoice);
        }

        // PAID invoice goes to any other status → create reversal journal
        if ($oldStatus === 'paid' && $newStatus !== 'paid') {
            $accountingService->createInvoiceReversalJournal($invoice, $newStatus);
        }
    }
}
