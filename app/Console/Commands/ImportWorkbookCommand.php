<?php

namespace App\Console\Commands;

use App\Services\WorkbookImportService;
use Illuminate\Console\Command;
use RuntimeException;

class ImportWorkbookCommand extends Command
{
    protected $signature = 'accounting:import-workbook
        {path=LAPORAN KEUANGAN AGUSTUS.xlsx : Path workbook Excel}
        {--fresh : Import is still idempotent; this flag documents the workbook refresh intent}';

    protected $description = 'Import workbook accounts and general journal into accounting tables';

    public function handle(WorkbookImportService $service): int
    {
        try {
            $result = $service->import($this->argument('path'));
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());
            return self::FAILURE;
        }

        $this->info("Periode: {$result['period']->label}");
        $this->info("COA diproses: {$result['accounts']}");
        $this->info("Jurnal dibuat: {$result['created']}");
        $this->info("Jurnal dilewati (sudah ada): {$result['skipped']}");
        return self::SUCCESS;
    }
}
