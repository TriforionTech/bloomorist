<?php

namespace App\Console\Commands;

use App\Models\AccountingPeriod;
use App\Services\WorkbookReconciliationService;
use Illuminate\Console\Command;
use RuntimeException;

class ReconcileWorkbookCommand extends Command
{
    protected $signature = 'accounting:reconcile-workbook
        {path=LAPORAN KEUANGAN AGUSTUS.xlsx : Path workbook Excel}
        {--period-id= : ID periode akuntansi yang dibandingkan}
        {--strict : Return a failure code when a value differs}';

    protected $description = 'Compare workbook financial totals with application reports';

    public function handle(WorkbookReconciliationService $service): int
    {
        $period = $this->period();
        if (!$period) {
            $this->error('Periode akuntansi tidak ditemukan. Gunakan --period-id atau buat periode Agustus 2026.');
            return self::FAILURE;
        }

        try {
            $result = $service->reconcile($this->argument('path'), $period);
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());
            return self::FAILURE;
        }

        $this->table(
            ['Sheet', 'Cell', 'Item', 'Workbook', 'Aplikasi', 'Selisih', 'Status'],
            $result['checks']->map(fn (array $check): array => [
                $check['sheet'],
                $check['cell'],
                $check['label'],
                $this->format($check['workbook']),
                $this->format($check['application']),
                $this->format($check['difference']),
                $check['passed'] ? 'PASS' : 'DIFF',
            ])->all(),
        );

        $passed = $result['checks']->where('passed', true)->count();
        $this->info("Rekonsiliasi selesai: {$passed}/{$result['checks']->count()} cocok.");

        return $result['passed'] || !$this->option('strict') ? self::SUCCESS : self::FAILURE;
    }

    private function period(): ?AccountingPeriod
    {
        if ($id = $this->option('period-id')) {
            return AccountingPeriod::find($id);
        }

        return AccountingPeriod::query()
            ->whereDate('start_date', '2026-08-01')
            ->whereDate('end_date', '2026-08-31')
            ->first();
    }

    private function format(mixed $value): string
    {
        return $value === null ? '-' : number_format((float) $value, 2, ',', '.');
    }
}
