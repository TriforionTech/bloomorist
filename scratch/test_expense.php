<?php
require __DIR__.'/../../vendor/autoload.php';
$app = require_once __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$count = App\Models\JournalItem::whereHas('coa', fn($q) => $q->where('kode_akun', 'like', '6%'))->count();
echo "Expense items count: $count\n";

$cashJournals = App\Models\JournalItem::whereHas('coa', fn($q) => $q->whereIn('kode_akun', ['1101', '1102']))
    ->pluck('journal_id')
    ->toArray();

echo "Cash journals count: " . count($cashJournals) . "\n";

$expenseCashJournals = App\Models\JournalItem::whereHas('coa', fn($q) => $q->where('kode_akun', 'like', '6%'))
    ->whereIn('journal_id', $cashJournals)
    ->with('coa')
    ->get();

echo "Expense cash items:\n";
foreach($expenseCashJournals as $item) {
    echo "Journal ID: {$item->journal_id} - COA: {$item->coa->kode_akun} - Debit: {$item->debit} - Kredit: {$item->kredit}\n";
}
