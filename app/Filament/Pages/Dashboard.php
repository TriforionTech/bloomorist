<?php

namespace App\Filament\Pages;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard\Actions\FilterAction;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersAction;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

class Dashboard extends BaseDashboard
{
    use HasFiltersAction;

    protected function getHeaderActions(): array
    {
        return [
            FilterAction::make()
                ->label('Filter Periode')
                ->modalHeading('Filter Periode Dashboard')
                ->modalDescription('Pilih rentang waktu lalu klik Terapkan untuk memperbarui seluruh data.')
                ->modalSubmitActionLabel('Terapkan Filter')
                ->schema([
                    Select::make('filter_preset')
                        ->label('Rentang Waktu')
                        ->options([
                            'today' => 'Hari Ini (Today)',
                            'yesterday' => 'Kemarin (Yesterday)',
                            'this_month' => 'Bulan Ini (This Month)',
                            'previous_month' => 'Bulan Lalu (Previous Month)',
                            'ytd' => 'Tahun Ini (This Year)',
                            'previous_year' => 'Tahun Lalu (Previous Year)',
                            'all' => 'Semua Waktu (All Time)',
                            'custom' => 'Kustom (Custom Range)',
                        ])
                        ->default('this_month')
                        ->live(),

                    Grid::make([
                        'default' => 1,
                        'sm' => 2,
                    ])
                        ->visible(fn (Get $get) => $get('filter_preset') === 'custom')
                        ->schema([
                            DatePicker::make('startDate')
                                ->label('Dari Tanggal')
                                ->native(false)
                                ->displayFormat('d M Y')
                                ->closeOnDateSelection(),

                            DatePicker::make('endDate')
                                ->label('Sampai Tanggal')
                                ->native(false)
                                ->displayFormat('d M Y')
                                ->closeOnDateSelection(),
                        ]),
                ]),
        ];
    }

    /**
     * Menggunakan sistem grid 12 kolom agar ukuran tiap widget bisa diatur lebih detail.
     */
    public function getColumns(): int|array
    {
        return [
            'default' => 1,
            'lg' => 12,
        ];
    }

    public function getSubheading(): string|Htmlable|null
    {
        $preset = $this->filters['filter_preset'] ?? 'this_month';
        $label = match ($preset) {
            'today' => 'Hari Ini',
            'yesterday' => 'Kemarin',
            'this_month' => 'Bulan Ini',
            'previous_month' => 'Bulan Lalu',
            'ytd' => 'Tahun Ini',
            'previous_year' => 'Tahun Lalu',
            'all' => 'Semua Waktu',
            'custom' => 'Kustom ('.($this->filters['startDate'] ?? '-').' s/d '.($this->filters['endDate'] ?? '-').')',
            default => 'Bulan Ini',
        };

        return new HtmlString("Pantau ringkasan performa penjualan dan inventaris Bloomorist. <span class='inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-primary-50 text-primary-700 dark:bg-primary-950 dark:text-primary-300 ml-1'>📅 Filter: {$label}</span>");
    }
}
