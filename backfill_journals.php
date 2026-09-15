<?php
use App\Models\Invoice;
use App\Services\AccountingService;

$accountingService = app(AccountingService::class);
$invoices = Invoice::where('status', 'paid')
    ->whereMonth('issued_date', 7)
    ->whereYear('issued_date', 2026)
    ->whereNotExists(function ($query) {
        $query->select(DB::raw(1))
              ->from('bl_general_journals_t')
              ->whereColumn('bl_general_journals_t.reference_id', 'bl_invoices_t.id')
              ->where('bl_general_journals_t.source_type', 'INVOICE');
    })
    ->get();

echo "Found " . $invoices->count() . " invoices to backfill.\n";
$count = 0;
foreach ($invoices as $invoice) {
    try {
        $res = $accountingService->createInvoicePaidJournal($invoice);
        if ($res) {
            $count++;
            echo "Backfilled INV " . $invoice->invoice_number . "\n";
        } else {
            echo "Failed INV " . $invoice->invoice_number . " (Returned null)\n";
        }
    } catch (\Exception $e) {
        echo "Error INV " . $invoice->invoice_number . ": " . $e->getMessage() . "\n";
    }
}
echo "Successfully backfilled " . $count . " journals for paid invoices.\n";
