<?php

namespace App\Filament\Pages;

use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Schemas\Components\Utilities\Get;

class Dashboard extends BaseDashboard
{
    use HasFiltersForm;

    public function filtersForm(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Grid::make([
                    'default' => 1,
                    'sm' => 3,
                ])
                    ->schema([
                        Select::make('filter_preset')
                            ->label('Filter By')
                            ->options([
                                'today' => 'Today',
                                'yesterday' => 'Yesterday',
                                'this_month' => 'This Month',
                                'previous_month' => 'Previous Month',
                                'ytd' => 'This Year',
                                'previous_year' => 'Previous Year',
                                'all' => 'All Time',
                                'custom' => 'Custom Range',
                            ])
                            ->default('this_month')
                            ->columnSpan(fn (Get $get) => $get('filter_preset') === 'custom' ? 1 : ['default' => 1, 'sm' => 3])
                            ->live(),
                        
                        DatePicker::make('startDate')
                            ->label('From')
                            ->native(false)
                            ->displayFormat('d M Y')
                            ->visible(fn (Get $get) => $get('filter_preset') === 'custom')
                            ->live(),

                        DatePicker::make('endDate')
                            ->label('Until')
                            ->native(false)
                            ->displayFormat('d M Y')
                            ->visible(fn (Get $get) => $get('filter_preset') === 'custom')
                            ->live(),
                    ])
            ]);
    }

    /**
     * Menggunakan sistem grid 12 kolom agar ukuran tiap widget bisa diatur lebih detail.
     */
    public function getColumns(): int | array
    {
        return [
            'default' => 1,
            'lg' => 12,
        ];
    }

    public function getSubheading(): string | \Illuminate\Contracts\Support\Htmlable | null
    {
        return 'Pantau ringkasan performa penjualan dan inventaris Bloomorist.';
    }
}
