<?php

namespace App\Filament\Resources\AccountingPeriods\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;

class AccountingPeriodForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Informasi Periode')
                    ->description('Tentukan rentang waktu untuk periode akuntansi ini.')
                    ->schema([
                        TextInput::make('label')
                            ->label('Nama Periode')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Contoh: Agustus 2026')
                            ->columnSpanFull(),

                        DatePicker::make('start_date')
                            ->label('Tanggal Mulai')
                            ->required()
                            ->displayFormat('d/m/Y')
                            ->native(false),

                        DatePicker::make('end_date')
                            ->label('Tanggal Selesai')
                            ->required()
                            ->displayFormat('d/m/Y')
                            ->native(false)
                            ->afterOrEqual('start_date'),
                    ])->columns(2),

                Section::make('Saldo & Persediaan Akhir')
                    ->description('Masukkan saldo awal kas dan nilai fisik stok akhir bulan.')
                    ->schema([
                        TextInput::make('opening_cash_balance')
                            ->label('Saldo Awal Kas & Bank')
                            ->required()
                            ->default(0)
                            ->prefix('Rp')
                            ->numeric()
                            ->helperText('Otomatis bisa di-0-kan untuk MVP, berjalan menerus.')
                            ->extraInputAttributes([
                                'inputmode' => 'numeric',
                            ]),

                        TextInput::make('closing_inventory_value')
                            ->label('Nilai Stock Opname Akhir')
                            ->prefix('Rp')
                            ->numeric()
                            ->placeholder('Masukkan nilai stok di akhir periode')
                            ->helperText('Wajib diisi sebelum melakukan Tutup Buku.')
                            ->extraInputAttributes([
                                'inputmode' => 'numeric',
                            ]),
                    ])->columns(2),
                    
                Section::make('Status Pembukuan')
                    ->schema([
                        Select::make('status')
                            ->options([
                                'open' => '🟢 OPEN (Bisa Input Transaksi)',
                                'closed' => '🔴 CLOSED (Tutup Buku - Terkunci)',
                            ])
                            ->default('open')
                            ->disabled() // Diubah via action Tutup Buku saja
                            ->dehydrated(false)
                            ->required(),
                    ])->visibleOn('edit'),
            ]);
    }
}
