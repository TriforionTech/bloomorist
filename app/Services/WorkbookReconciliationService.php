<?php

namespace App\Services;

use App\Models\AccountingPeriod;
use Illuminate\Support\Collection;
use RuntimeException;
use ZipArchive;

class WorkbookReconciliationService
{
    public function reconcile(string $path, AccountingPeriod $period): array
    {
        $workbook = $this->readWorkbook($path);
        $income = app(AccountingService::class)->getIncomeStatement(
            $period->start_date->copy()->startOfDay(),
            $period->end_date->copy()->endOfDay(),
        );
        $balance = app(AccountingService::class)->getBalanceSheet($period->end_date->copy()->endOfDay());
        $trialBalance = app(TrialBalanceService::class)->getForPeriod($period->id);
        $cashFlow = app(CashFlowService::class)->getForPeriod($period->id);

        $checks = collect([
            $this->check($workbook, 'Dashboard', 'A6', 'Penjualan bersih', $income['penjualan_bersih']),
            $this->check($workbook, 'Dashboard', 'B6', 'Total beban', $income['total_beban']),
            $this->check($workbook, 'Dashboard', 'C6', 'Laba bersih', $income['laba_rugi']),
            $this->check($workbook, 'Dashboard', 'D6', 'Kas dan bank', $cashFlow['trial_balance_cash']),
            $this->check($workbook, 'Laba Rugi', 'C8', 'Penjualan bersih', $income['penjualan_bersih']),
            $this->check($workbook, 'Laba Rugi', 'C18', 'HPP', $income['hpp']),
            $this->check($workbook, 'Laba Rugi', 'C33', 'Beban operasional', $income['beban_operasional']),
            $this->check($workbook, 'Laba Rugi', 'C42', 'Laba bersih', $income['laba_rugi']),
            $this->check($workbook, 'Neraca', 'C23', 'Total aset', $balance['total_aset']),
            $this->check($workbook, 'Neraca', 'C35', 'Liabilitas dan ekuitas', $balance['total_kewajiban_ekuitas']),
            $this->check($workbook, 'Neraca Saldo', 'C39', 'Total debit neraca saldo', $trialBalance['total_debit']),
            $this->check($workbook, 'Neraca Saldo', 'D39', 'Total kredit neraca saldo', $trialBalance['total_credit']),
            $this->check($workbook, 'Arus Kas', 'D33', 'Saldo kas akhir', $cashFlow['ending_cash']),
        ]);

        return [
            'period' => $period,
            'checks' => $checks,
            'passed' => $checks->every(fn (array $check): bool => $check['passed']),
            'workbook' => $path,
        ];
    }

    private function check(array $workbook, string $sheet, string $cell, string $label, float|int|null $application): array
    {
        $workbookValue = $workbook[$sheet][$cell] ?? null;
        $difference = $workbookValue === null || $application === null
            ? null
            : (float) $application - (float) $workbookValue;

        return [
            'sheet' => $sheet,
            'cell' => $cell,
            'label' => $label,
            'workbook' => $workbookValue,
            'application' => $application,
            'difference' => $difference,
            'passed' => $difference !== null && abs($difference) < 0.01,
        ];
    }

    public function readWorkbook(string $path): array
    {
        if (!is_file($path)) {
            throw new RuntimeException("Workbook tidak ditemukan: {$path}");
        }

        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException('Workbook Excel tidak dapat dibuka.');
        }

        $sharedStrings = $this->sharedStrings($zip);
        $workbook = simplexml_load_string($zip->getFromName('xl/workbook.xml'));
        $relationships = simplexml_load_string($zip->getFromName('xl/_rels/workbook.xml.rels'));
        $namespaces = $workbook->getNamespaces(true);
        $relationshipNamespace = $relationships->getNamespaces(true)[''] ?? 'http://schemas.openxmlformats.org/package/2006/relationships';
        $workbook->registerXPathNamespace('main', $namespaces['']);
        $relationships->registerXPathNamespace('rel', $relationshipNamespace);
        $relationshipMap = [];
        foreach ($relationships->xpath('//rel:Relationship') as $relationship) {
            $relationshipMap[(string) $relationship['Id']] = 'xl/' . ltrim((string) $relationship['Target'], '/');
        }

        $result = [];
        foreach ($workbook->xpath('//main:sheets/main:sheet') as $sheet) {
            $attributes = $sheet->attributes();
            $relationAttributes = $sheet->attributes($namespaces['r']);
            $sheetPath = $relationshipMap[(string) $relationAttributes['id']] ?? null;
            if (!$sheetPath) {
                continue;
            }
            $result[(string) $attributes['name']] = $this->sheetValues(
                simplexml_load_string($zip->getFromName($sheetPath)),
                $sharedStrings,
            );
        }

        $zip->close();
        return $result;
    }

    private function sharedStrings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if ($xml === false) {
            return [];
        }

        $strings = [];
        $document = simplexml_load_string($xml);
        foreach ($document->si as $item) {
            $strings[] = implode('', array_map('strval', $item->xpath('.//*[local-name()="t"]') ?: []));
        }
        return $strings;
    }

    private function sheetValues(\SimpleXMLElement $sheet, array $sharedStrings): array
    {
        $values = [];
        foreach ($sheet->xpath('//*[local-name()="c"]') ?: [] as $cell) {
            $attributes = $cell->attributes();
            $raw = (string) ($cell->v ?? '');
            if ((string) ($attributes['t'] ?? '') === 's') {
                $value = $sharedStrings[(int) $raw] ?? null;
            } elseif ((string) ($attributes['t'] ?? '') === 'str' || (string) ($attributes['t'] ?? '') === 'inlineStr') {
                $value = (string) ($cell->is ?? $cell->v ?? '');
            } else {
                $value = is_numeric($raw) ? (float) $raw : ($raw === '' ? null : $raw);
            }
            $values[(string) $attributes['r']] = $value;
        }
        return $values;
    }
}
