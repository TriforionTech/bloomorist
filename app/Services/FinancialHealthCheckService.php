<?php

namespace App\Services;

use Carbon\Carbon;

class FinancialHealthCheckService
{
    public function __construct(
        private readonly TrialBalanceService $trialBalanceService,
        private readonly AccountingService $accountingService,
        private readonly CashFlowService $cashFlowService,
    ) {
    }

    /**
     * Validate the trial balance for an accounting period.
     */
    public function checkTrialBalance(int $periodId): array
    {
        $trialBalance = $this->trialBalanceService->getForPeriod($periodId);

        return [
            'is_healthy' => $trialBalance['is_balanced'],
            'check' => 'trial_balance',
            'message' => $trialBalance['is_balanced']
                ? 'Neraca Saldo seimbang.'
                : 'Neraca Saldo tidak seimbang.',
            'total_debit' => $trialBalance['total_debit'],
            'total_credit' => $trialBalance['total_credit'],
            'difference' => $trialBalance['total_debit'] - $trialBalance['total_credit'],
        ];
    }

    /**
     * Validate that total assets equal liabilities plus equity at period end.
     */
    public function checkBalanceSheet(int $periodId): array
    {
        $period = \App\Models\AccountingPeriod::findOrFail($periodId);
        $balanceSheet = $this->accountingService->getBalanceSheet(
            Carbon::parse($period->getRawOriginal('end_date'), config('app.timezone')),
        );
        $difference = (float) $balanceSheet['total_aset'] - (float) $balanceSheet['total_kewajiban_ekuitas'];
        $isBalanced = abs($difference) < 0.01;

        return [
            'is_healthy' => $isBalanced,
            'check' => 'balance_sheet',
            'message' => $isBalanced
                ? 'Neraca seimbang.'
                : 'Neraca tidak seimbang.',
            'total_assets' => $balanceSheet['total_aset'],
            'total_liabilities_equity' => $balanceSheet['total_kewajiban_ekuitas'],
            'difference' => $difference,
        ];
    }

    public function checkCashFlow(int $periodId): array
    {
        $cashFlow = $this->cashFlowService->getForPeriod($periodId);

        return [
            'is_healthy' => $cashFlow['is_reconciled'],
            'check' => 'cash_flow',
            'message' => $cashFlow['is_reconciled']
                ? 'Saldo Arus Kas cocok dengan saldo Kas/Bank.'
                : 'Saldo Arus Kas tidak cocok dengan saldo Kas/Bank.',
            'ending_cash' => $cashFlow['ending_cash'],
            'trial_balance_cash' => $cashFlow['trial_balance_cash'],
            'difference' => $cashFlow['difference'],
        ];
    }
}
