<?php

namespace App\Services;

use App\Models\AccountingPeriod;
use App\Models\MonthlySummary;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PeriodClosingService
{
    public function __construct(
        private readonly FinancialHealthCheckService $healthCheck,
        private readonly AccountingService $accountingService,
        private readonly CashFlowService $cashFlowService,
    ) {
    }

    public function close(AccountingPeriod $period): MonthlySummary
    {
        if ($period->isClosed()) {
            throw new RuntimeException('Periode sudah ditutup.');
        }

        if ($period->closing_inventory_value === null) {
            throw new RuntimeException('Nilai stock opname akhir wajib diisi.');
        }

        $checks = [
            $this->healthCheck->checkTrialBalance($period->id),
            $this->healthCheck->checkBalanceSheet($period->id),
            $this->healthCheck->checkCashFlow($period->id),
        ];
        $failed = collect($checks)->first(fn (array $check) => !$check['is_healthy']);
        if ($failed) {
            throw new RuntimeException("Tutup periode ditolak: {$failed['message']}");
        }

        return DB::transaction(function () use ($period) {
            $income = $this->accountingService->getIncomeStatement(
                $period->start_date->startOfDay(),
                $period->end_date->endOfDay(),
            );
            $cashFlow = $this->cashFlowService->getForPeriod($period->id);

            $period->update([
                'status' => 'closed',
                'closed_at' => now(),
            ]);

            return MonthlySummary::updateOrCreate(
                ['accounting_period_id' => $period->id],
                [
                    'net_sales' => $income['penjualan_bersih'],
                    'cogs' => $income['hpp'],
                    'gross_profit' => $income['laba_kotor'],
                    'operating_expenses' => $income['beban_operasional'],
                    'net_income' => $income['laba_rugi'],
                    'ending_cash_bank' => $cashFlow['ending_cash'],
                    'generated_at' => now(),
                ],
            );
        });
    }
}
