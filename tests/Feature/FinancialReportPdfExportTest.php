<?php

namespace Tests\Feature;

use App\Models\AccountingPeriod;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Tests\TestCase;

class FinancialReportPdfExportTest extends TestCase
{
    public function test_financial_report_templates_render_with_dompdf(): void
    {
        $generated = '17 Sep 2026 10:00';
        $period = new AccountingPeriod([
            'label' => 'September 2026',
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-30',
        ]);

        $income = [
            'start_date' => Carbon::parse('2026-09-01'),
            'end_date' => Carbon::parse('2026-09-30'),
            'balances_by_code' => ['4101' => 10000, '4102' => 1000, '5101' => 4000, '5102' => 500, '5103' => 200, '6101' => 800, '4103' => 100, '4104' => 50],
            'penjualan_bersih' => 9000,
            'pembelian_bersih' => 3500,
            'beban_angkut_pembelian' => 200,
            'persediaan_awal' => 2000,
            'barang_tersedia_dijual' => 5700,
            'persediaan_akhir' => 500,
            'hpp' => 5200,
            'laba_kotor' => 3800,
            'beban_operasional' => 800,
            'laba_usaha' => 3000,
            'pendapatan_luar_usaha' => 150,
            'laba_rugi' => 3150,
        ];
        $balance = [
            'as_of' => Carbon::parse('2026-09-30'),
            'balances_by_code' => ['1101' => 5000, '1104' => 500, '1105' => 4000, '1106' => 1000, '2101' => 2000, '3101' => 6500],
            'aset_groups' => ['Aktiva Lancar' => [['saldo' => 5500]], 'Aktiva Tetap' => [['saldo' => 4000]], 'Aktiva Tetap (Kontra)' => [['saldo' => -1000]]],
            'total_aset' => 8500,
            'total_kewajiban' => 2000,
            'laba_ditahan' => 1000,
            'total_ekuitas' => 6500,
            'total_kewajiban_ekuitas' => 8500,
            'is_balanced' => true,
        ];
        $cashFlow = [
            'period' => $period,
            'operating' => [['key' => 'sales_receipts', 'label' => 'Penerimaan dari Penjualan', 'amount' => 3000]],
            'investing' => [['key' => 'fixed_assets', 'label' => 'Pembelian Peralatan', 'amount' => -500]],
            'financing' => [['key' => 'owner_capital', 'label' => 'Setoran Modal Pemilik', 'amount' => 1000]],
            'operating_total' => 3000,
            'investing_total' => -500,
            'financing_total' => 1000,
            'opening_cash' => 1000,
            'net_change' => 3500,
            'ending_cash' => 4500,
            'is_reconciled' => true,
            'difference' => 0,
        ];

        $documents = [
            ['pdf.income-statement', ['data' => $income, 'company' => 'Bloomorist', 'generated' => $generated, 'periodLabel' => $period->label]],
            ['pdf.balance-sheet', ['data' => $balance, 'company' => 'Bloomorist', 'generated' => $generated]],
            ['pdf.cash-flow', ['data' => $cashFlow, 'company' => 'Bloomorist', 'generated' => $generated]],
        ];

        foreach ($documents as [$view, $data]) {
            $output = Pdf::loadView($view, $data)
                ->setPaper('a4', 'portrait')
                ->setOption('isHtml5ParserEnabled', true)
                ->output();

            $this->assertStringStartsWith('%PDF-', $output, "{$view} harus dihasilkan oleh Dompdf.");
            $this->assertGreaterThan(1000, strlen($output), "{$view} tidak boleh menghasilkan PDF kosong.");
        }
    }
}
