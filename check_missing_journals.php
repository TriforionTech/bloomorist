<?php
$count = DB::table('bl_invoices_t')
    ->where('status', 'paid')
    ->whereMonth('issued_date', 7)
    ->whereYear('issued_date', 2026)
    ->whereNotExists(function ($query) {
        $query->select(DB::raw(1))
              ->from('bl_general_journals_t')
              ->whereColumn('bl_general_journals_t.reference_id', 'bl_invoices_t.id')
              ->where('bl_general_journals_t.source_type', 'INVOICE');
    })
    ->count();
echo "Missing journals count: " . $count . "\n";
