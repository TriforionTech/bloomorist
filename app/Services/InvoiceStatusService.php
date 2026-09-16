<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\GeneralJournal;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;

class InvoiceStatusService
{
    /**
     * Allowed status transitions (state machine).
     *
     * - pending → paid       : Potong stok + Journal penjualan
     * - pending → cancelled  : Hanya ubah status (belum ada stok/journal)
     * - paid → cancelled     : Kembalikan stok + Reversing Journal
     */
    private const ALLOWED_TRANSITIONS = [
        'pending'   => ['paid', 'cancelled'],
        'paid'      => ['cancelled'],
        'cancelled' => [], // Final — tidak bisa ke mana-mana
    ];

    /**
     * Handle status change with strict state machine validation.
     *
     * Seluruh operasi (stok, journal, status update) di dalam
     * satu DB::transaction + lockForUpdate() untuk race condition safety.
     */
    public function changeStatus(Invoice $invoice, string $newStatus): void
    {
        $oldStatus = $invoice->status;

        // Skip jika status tidak berubah
        if ($oldStatus === $newStatus) {
            return;
        }

        // Validate allowed transition
        $allowed = self::ALLOWED_TRANSITIONS[$oldStatus] ?? [];
        if (! in_array($newStatus, $allowed, true)) {
            throw new \RuntimeException(
                "Transisi status dari " . strtoupper($oldStatus)
                . " ke " . strtoupper($newStatus) . " tidak diperbolehkan."
            );
        }

        DB::transaction(function () use ($invoice, $oldStatus, $newStatus) {
            // Lock record to prevent race condition
            $invoice = Invoice::lockForUpdate()->findOrFail($invoice->id);

            // Re-validate setelah lock (bisa saja berubah antara check dan lock)
            if ($invoice->status !== $oldStatus) {
                throw new \RuntimeException(
                    "Status invoice telah berubah (race condition detected). Silakan coba lagi."
                );
            }

            // Recalculate totals after invoice items have been persisted and
            // before the accounting journal reads grand_total.
            $invoice->load('items');
            $invoice->save();

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
     *
     * - pending → paid      : Potong stok (sale)
     * - paid → cancelled    : Kembalikan stok (return/reversal)
     * - pending → cancelled : TIDAK ada perubahan stok
     */
    protected function processStockMutation(Invoice $invoice, string $oldStatus, string $newStatus): void
    {
        $userId = \Filament\Facades\Filament::auth()->id() ?? 1;

        // Pending → Paid: potong stok
        if ($oldStatus === 'pending' && $newStatus === 'paid') {
            $items = $invoice->items()->with('product')->get();

            foreach ($items as $item) {
                if ($item->product) {
                    if ($item->product->stok < $item->quantity) {
                        throw new \RuntimeException(
                            "Stok tidak mencukupi untuk produk: {$item->product->nama}. "
                            . "Stok saat ini: {$item->product->stok}, dibutuhkan: {$item->quantity}."
                        );
                    }

                    $item->product->decrement('stok', $item->quantity);

                    StockMovement::create([
                        'product_id'   => $item->product->id,
                        'type'         => 'sale',
                        'quantity'     => $item->quantity,
                        'reference_id' => $invoice->invoice_number,
                        'notes'        => 'Auto: Invoice paid - ' . $invoice->invoice_number,
                        'user_id'      => $userId,
                    ]);
                }
            }
        }

        // Paid → Cancelled: kembalikan stok
        if ($oldStatus === 'paid' && $newStatus === 'cancelled') {
            $items = $invoice->items()->with('product')->get();

            foreach ($items as $item) {
                if ($item->product) {
                    $item->product->increment('stok', $item->quantity);

                    StockMovement::create([
                        'product_id'   => $item->product->id,
                        'type'         => 'return',
                        'quantity'     => $item->quantity,
                        'reference_id' => $invoice->invoice_number,
                        'notes'        => 'Auto: Pembatalan Invoice #' . $invoice->invoice_number,
                        'user_id'      => $userId,
                    ]);
                }
            }
        }

        // Pending → Cancelled: TIDAK ada perubahan stok (tidak pernah dipotong)
    }

    /**
     * Generate Jurnal Umum otomatis berdasarkan perubahan status.
     *
     * - pending → paid      : Debit Kas, Kredit Pendapatan (idempotent)
     * - paid → cancelled    : Reversing journal (Debit Pendapatan, Kredit Kas)
     * - pending → cancelled : TIDAK ada journal
     */
    protected function generateAccountingJournal(Invoice $invoice, string $oldStatus, string $newStatus): void
    {
        $accountingService = app(AccountingService::class);

        // Pending → Paid: create revenue journal (idempotent)
        if ($oldStatus === 'pending' && $newStatus === 'paid') {
            $accountingService->createInvoicePaidJournal($invoice);
        }

        // Paid → Cancelled: create reversal journal
        if ($oldStatus === 'paid' && $newStatus === 'cancelled') {
            $accountingService->createInvoiceReversalJournal($invoice, 'cancelled');
        }
    }
}
