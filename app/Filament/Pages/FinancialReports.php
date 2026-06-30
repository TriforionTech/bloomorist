<?php

namespace App\Filament\Pages;

use App\Services\AccountingService;
use BackedEnum;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;
use UnitEnum;

class FinancialReports extends Page implements HasSchemas
{
    use InteractsWithSchemas;

    protected static ?string $title = 'Financial Report';
    protected static ?string $navigationLabel = 'Financial Report';
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';
    protected static string|UnitEnum|null $navigationGroup = 'Accounting & Finances';
    protected static ?int $navigationSort = 4;

    protected string $view = 'filament.pages.financial-reports';

    // Income Statement filters
    public string $incomeFilter = 'bulan_ini';
    public ?string $incomeStartDate = null;
    public ?string $incomeEndDate = null;

    // Balance Sheet filter
    public ?string $balanceAsOf = null;

    // Active tab
    public string $activeTab = 'laba-rugi';

    /**
     * Only superadmin can access this page.
     */
    public static function canAccess(): bool
    {
        return Filament::auth()->user()?->is_super_admin ?? false;
    }

    public function mount(): void
    {
        $this->balanceAsOf = now()->format('Y-m-d');
    }

    /**
     * Filter schema for Income Statement tab.
     */
    public function incomeFilterSchema(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Filter Laporan Laba Rugi')
                ->schema([
                    Select::make('incomeFilter')
                        ->label('Periode')
                        ->options([
                            'bulan_ini' => 'Bulan Ini',
                            'tahun_ini' => 'Tahun Ini',
                            'custom'    => 'Custom Range',
                        ])
                        ->native(false)
                        ->live(),

                    DatePicker::make('incomeStartDate')
                        ->label('Dari Tanggal')
                        ->native(false)
                        ->visible(fn () => $this->incomeFilter === 'custom')
                        ->live(),

                    DatePicker::make('incomeEndDate')
                        ->label('Sampai Tanggal')
                        ->native(false)
                        ->visible(fn () => $this->incomeFilter === 'custom')
                        ->live(),
                ])
                ->columns(3),
        ]);
    }

    /**
     * Filter schema for Balance Sheet tab.
     */
    public function balanceFilterSchema(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Filter Neraca')
                ->schema([
                    DatePicker::make('balanceAsOf')
                        ->label('Per Tanggal (As Of)')
                        ->native(false)
                        ->default(now())
                        ->live(),
                ])
                ->columns(1),
        ]);
    }

    /**
     * Get Income Statement data based on current filter.
     */
    public function getIncomeStatementData(): array
    {
        [$start, $end] = $this->getIncomeDateRange();

        return app(AccountingService::class)->getIncomeStatement($start, $end);
    }

    /**
     * Get Balance Sheet data based on current filter.
     */
    public function getBalanceSheetData(): array
    {
        $asOf = $this->balanceAsOf
            ? Carbon::parse($this->balanceAsOf)
            : now();

        return app(AccountingService::class)->getBalanceSheet($asOf);
    }

    /**
     * Resolve date range from income filter selection.
     */
    private function getIncomeDateRange(): array
    {
        return match ($this->incomeFilter) {
            'bulan_ini' => [now()->startOfMonth(), now()->endOfMonth()],
            'tahun_ini' => [now()->startOfYear(), now()->endOfYear()],
            'custom'    => [
                $this->incomeStartDate ? Carbon::parse($this->incomeStartDate) : now()->startOfMonth(),
                $this->incomeEndDate ? Carbon::parse($this->incomeEndDate) : now()->endOfMonth(),
            ],
            default => [now()->startOfMonth(), now()->endOfMonth()],
        };
    }

    /**
     * Download Income Statement as PDF.
     */
    public function downloadIncomeStatementPdf()
    {
        $data = $this->getIncomeStatementData();

        $pdf = Pdf::loadView('pdf.income-statement', [
            'data'      => $data,
            'company'   => 'Bloomorist',
            'generated' => now()->format('d M Y H:i'),
        ]);

        return response()->streamDownload(
            fn () => print($pdf->output()),
            'laporan-laba-rugi-' . now()->format('Y-m-d') . '.pdf'
        );
    }

    /**
     * Download Income Statement as CSV.
     */
    public function downloadIncomeStatementCsv(): StreamedResponse
    {
        $data = $this->getIncomeStatementData();

        return response()->streamDownload(function () use ($data) {
            $handle = fopen('php://output', 'w');

            // UTF-8 BOM for Excel compatibility
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, ['LAPORAN LABA RUGI - BLOOMORIST']);
            fputcsv($handle, ['Periode: ' . $data['start_date']->format('d M Y') . ' - ' . $data['end_date']->format('d M Y')]);
            fputcsv($handle, []);

            // Pendapatan
            fputcsv($handle, ['PENDAPATAN']);
            fputcsv($handle, ['Kode Akun', 'Nama Akun', 'Jumlah']);
            foreach ($data['pendapatan'] as $item) {
                fputcsv($handle, [$item['kode_akun'], $item['nama_akun'], $item['saldo']]);
            }
            fputcsv($handle, ['', 'Total Pendapatan', $data['total_pendapatan']]);
            fputcsv($handle, []);

            // Beban
            fputcsv($handle, ['BEBAN']);
            fputcsv($handle, ['Kode Akun', 'Nama Akun', 'Jumlah']);
            foreach ($data['beban'] as $item) {
                fputcsv($handle, [$item['kode_akun'], $item['nama_akun'], $item['saldo']]);
            }
            fputcsv($handle, ['', 'Total Beban', $data['total_beban']]);
            fputcsv($handle, []);

            // Laba/Rugi
            fputcsv($handle, ['', 'LABA/RUGI BERSIH', $data['laba_rugi']]);

            fclose($handle);
        }, 'laporan-laba-rugi-' . now()->format('Y-m-d') . '.csv');
    }

    /**
     * Download Balance Sheet as PDF.
     */
    public function downloadBalanceSheetPdf()
    {
        $data = $this->getBalanceSheetData();

        $pdf = Pdf::loadView('pdf.balance-sheet', [
            'data'      => $data,
            'company'   => 'Bloomorist',
            'generated' => now()->format('d M Y H:i'),
        ]);

        return response()->streamDownload(
            fn () => print($pdf->output()),
            'neraca-' . now()->format('Y-m-d') . '.pdf'
        );
    }

    /**
     * Download Balance Sheet as CSV.
     */
    public function downloadBalanceSheetCsv(): StreamedResponse
    {
        $data = $this->getBalanceSheetData();

        return response()->streamDownload(function () use ($data) {
            $handle = fopen('php://output', 'w');

            // UTF-8 BOM
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, ['NERACA KEUANGAN - BLOOMORIST']);
            fputcsv($handle, ['Per Tanggal: ' . $data['as_of']->format('d M Y')]);
            fputcsv($handle, []);

            // Aset
            fputcsv($handle, ['ASET']);
            fputcsv($handle, ['Kode Akun', 'Nama Akun', 'Jumlah']);
            foreach ($data['aset'] as $item) {
                fputcsv($handle, [$item['kode_akun'], $item['nama_akun'], $item['saldo']]);
            }
            fputcsv($handle, ['', 'Total Aset', $data['total_aset']]);
            fputcsv($handle, []);

            // Kewajiban
            fputcsv($handle, ['KEWAJIBAN']);
            fputcsv($handle, ['Kode Akun', 'Nama Akun', 'Jumlah']);
            foreach ($data['kewajiban'] as $item) {
                fputcsv($handle, [$item['kode_akun'], $item['nama_akun'], $item['saldo']]);
            }
            fputcsv($handle, ['', 'Total Kewajiban', $data['total_kewajiban']]);
            fputcsv($handle, []);

            // Ekuitas
            fputcsv($handle, ['EKUITAS']);
            fputcsv($handle, ['Kode Akun', 'Nama Akun', 'Jumlah']);
            foreach ($data['ekuitas'] as $item) {
                fputcsv($handle, [$item['kode_akun'], $item['nama_akun'], $item['saldo']]);
            }
            fputcsv($handle, ['', 'Laba Ditahan / Retained Earnings', $data['laba_ditahan']]);
            fputcsv($handle, ['', 'Total Ekuitas', $data['total_ekuitas']]);
            fputcsv($handle, []);

            // Summary
            fputcsv($handle, ['', 'Total Kewajiban + Ekuitas', $data['total_kewajiban_ekuitas']]);
            fputcsv($handle, ['', 'Neraca Balance', $data['is_balanced'] ? 'YA' : 'TIDAK']);

            fclose($handle);
        }, 'neraca-' . now()->format('Y-m-d') . '.csv');
    }
}
