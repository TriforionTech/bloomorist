<?php
use App\Models\Invoice;
use App\Services\AccountingService;

$accountingService = app(AccountingService::class);
$invoice = Invoice::where('invoice_number', '0307263812')->first();
try {
    $res = $accountingService->createInvoicePaidJournal($invoice);
    echo "Result for invoice 0307263812:\n";
    var_dump($res);
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
