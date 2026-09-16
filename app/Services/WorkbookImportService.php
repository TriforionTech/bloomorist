<?php

namespace App\Services;

use App\Models\AccountingPeriod;
use App\Models\ChartOfAccount;
use App\Models\GeneralJournal;
use App\Models\JournalItem;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class WorkbookImportService
{
    public function __construct(private readonly WorkbookReconciliationService $workbookReader)
    {
    }

    public function import(string $path): array
    {
        $workbook = $this->workbookReader->readWorkbook($path);
        $accounts = $this->importAccounts($workbook['Daftar Akun'] ?? []);
        $period = AccountingPeriod::updateOrCreate(
            ['start_date' => '2026-08-01', 'end_date' => '2026-08-31'],
            ['label' => 'Agustus 2026', 'status' => 'open'],
        );

        $created = 0;
        $skipped = 0;
        foreach ($this->journalGroups($workbook['Jurnal Umum'] ?? []) as $group) {
            $noBukti = $group['no_bukti'] ?: $this->generatedNoBukti($group);
            $existing = GeneralJournal::where('no_bukti', $noBukti)->first();
            if ($existing && $existing->keterangan === $group['description']) {
                $skipped++;
                continue;
            }
            if ($existing) {
                $noBukti = $this->generatedNoBukti($group);
                $existing = GeneralJournal::where('no_bukti', $noBukti)->first();
                if ($existing && $existing->keterangan === $group['description']) {
                    $skipped++;
                    continue;
                }
                if ($existing) {
                    $noBukti .= '-' . substr(sha1($group['description']), 0, 6);
                }
            }

            $debit = array_sum(array_column($group['items'], 'debit'));
            $credit = array_sum(array_column($group['items'], 'kredit'));
            if ($debit <= 0 || abs($debit - $credit) > 0.01) {
                throw new RuntimeException("Jurnal '{$group['description']}' tidak seimbang.");
            }

            DB::transaction(function () use ($group, $noBukti, $accounts): void {
                $journal = GeneralJournal::create([
                    'tanggal' => $group['date'],
                    'no_bukti' => $noBukti,
                    'keterangan' => $group['description'],
                    'source_type' => 'WORKBOOK_IMPORT',
                ]);
                foreach ($group['items'] as $item) {
                    $account = $accounts[$item['code']] ?? throw new RuntimeException("Akun {$item['code']} tidak ditemukan.");
                    JournalItem::create([
                        'journal_id' => $journal->id,
                        'coa_id' => $account->id,
                        'kode_coa' => $item['code'],
                        'debit' => $item['debit'],
                        'kredit' => $item['kredit'],
                    ]);
                }
            });
            $created++;
        }

        return ['period' => $period, 'accounts' => count($accounts), 'created' => $created, 'skipped' => $skipped];
    }

    private function importAccounts(array $rows): array
    {
        $accounts = [];
        foreach ($rows as $cell => $value) {
            if (!preg_match('/^A(\d+)$/', $cell, $match) || !is_numeric($value)) {
                continue;
            }
            $row = (int) $match[1];
            $code = $this->code($value);
            $name = trim((string) ($rows["B{$row}"] ?? ''));
            if ($name === '') {
                continue;
            }
            $category = trim((string) ($rows["C{$row}"] ?? ''));
            if ($category === '') {
                throw new RuntimeException("Kategori akun {$code} kosong.");
            }
            $normal = strcasecmp(trim((string) ($rows["D{$row}"] ?? '')), 'Kredit') === 0 ? 'Kredit' : 'Debit';
            $account = ChartOfAccount::updateOrCreate(
                ['kode_akun' => $code],
                ['nama_akun' => $name, 'kategori' => $category, 'saldo_normal' => $normal],
            );
            $accounts[$code] = $account;
        }
        return $accounts;
    }

    private function journalGroups(array $rows): array
    {
        $groups = [];
        $current = null;
        foreach ($rows as $cell => $value) {
            if (!preg_match('/^A(\d+)$/', $cell, $match) || !is_numeric($value)) {
                continue;
            }
            $row = (int) $match[1];
            $date = $this->date($value);
            $code = $this->code($rows["D{$row}"] ?? '');
            $description = trim((string) ($rows["C{$row}"] ?? ''));
            if (!$date || !$code || $description === '') {
                continue;
            }
            $reference = trim((string) ($rows["B{$row}"] ?? ''));
            $key = $date . '|' . strtolower(preg_replace('/\s+/', ' ', $reference ?: $description));
            if ($current === null || $current['key'] !== $key) {
                if ($current !== null) {
                    $groups[] = $current;
                }
                $current = [
                    'key' => $key,
                    'date' => $date,
                    'no_bukti' => trim((string) ($rows["B{$row}"] ?? '')) ?: null,
                    'description' => $description,
                    'items' => [],
                ];
            }
            $current['items'][] = [
                'code' => $code,
                'debit' => (float) ($rows["F{$row}"] ?? 0),
                'kredit' => (float) ($rows["G{$row}"] ?? 0),
            ];
        }
        if ($current !== null) {
            $groups[] = $current;
        }
        return $groups;
    }

    private function generatedNoBukti(array $group): string
    {
        return 'WB-' . Carbon::parse($group['date'])->format('ymd') . '-' . substr(sha1($group['key']), 0, 10);
    }

    private function code(mixed $value): string
    {
        return is_numeric($value) ? (string) (int) $value : trim((string) $value);
    }

    private function date(mixed $value): ?string
    {
        if (is_numeric($value)) {
            return Carbon::create(1899, 12, 30)->addDays((int) $value)->toDateString();
        }
        return $value ? Carbon::parse((string) $value)->toDateString() : null;
    }

}
