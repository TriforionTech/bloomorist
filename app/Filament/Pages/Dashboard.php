<?php

namespace App\Filament\Pages;

use App\Services\DashboardPeriod;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\View\View;

class Dashboard extends BaseDashboard
{
    use HasFiltersForm;

    /**
     * Filter periode global: dropdown di header, otomatis diterapkan (live) tanpa tombol Apply.
     * Kunci state dipertahankan (`filter_preset`, `startDate`, `endDate`) agar widget lama tetap kompatibel.
     */
    public function filtersForm(Schema $schema): Schema
    {
        $isCustom = ($this->filters['filter_preset'] ?? DashboardPeriod::DEFAULT_PRESET) === 'custom';
        $cols = $isCustom ? 3 : 1;

        return $schema
            ->columns([
                'default' => 1,
                'sm' => $cols,
                'md' => $cols,
                'lg' => $cols,
                'xl' => $cols,
                '2xl' => $cols,
            ])
            ->components([
                Select::make('filter_preset')
                    ->hiddenLabel()
                    ->placeholder('Pilih periode')
                    ->prefixIcon(Heroicon::OutlinedCalendarDays)
                    ->options(DashboardPeriod::presetOptions())
                    ->default(DashboardPeriod::DEFAULT_PRESET)
                    ->selectablePlaceholder(false)
                    ->native(false)
                    ->live(),

                DatePicker::make('startDate')
                    ->hiddenLabel()
                    ->placeholder('Dari tanggal')
                    ->native(false)
                    ->displayFormat('d M Y')
                    ->closeOnDateSelection()
                    ->maxDate(fn (Get $get): ?string => $get('endDate') ?: null)
                    ->visible(fn (Get $get): bool => $get('filter_preset') === 'custom')
                    ->live(),

                DatePicker::make('endDate')
                    ->hiddenLabel()
                    ->placeholder('Sampai tanggal')
                    ->native(false)
                    ->displayFormat('d M Y')
                    ->closeOnDateSelection()
                    ->minDate(fn (Get $get): ?string => $get('startDate') ?: null)
                    ->visible(fn (Get $get): bool => $get('filter_preset') === 'custom')
                    ->live(),
            ]);
    }

    /**
     * Header kustom: judul, periode aktif, keterangan pembaruan, dan dropdown filter.
     */
    public function getHeader(): ?View
    {
        $period = DashboardPeriod::fromFilters($this->filters);

        return view('filament.pages.dashboard-header', [
            'heading' => $this->getHeading(),
            'period' => $period,
            'updatedAt' => now()->format('H.i'),
            'filtersForm' => $this->getFiltersForm(),
        ]);
    }

    /**
     * Filter sudah dirender di header, jadi konten hanya berisi widget.
     */
    public function content(Schema $schema): Schema
    {
        return $schema->components([
            $this->getWidgetsContentComponent(),
        ]);
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
}
