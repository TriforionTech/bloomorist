<?php

namespace App\Filament\Resources\ChartOfAccounts\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ChartOfAccountForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('kode_akun')
                ->label('Kode Akun')
                ->required()
                ->unique(ignoreRecord: true)
                ->maxLength(20)
                ->placeholder('Contoh: 1010'),

            TextInput::make('nama_akun')
                ->label('Nama Akun')
                ->required()
                ->maxLength(255)
                ->placeholder('Contoh: Kas & Bank'),

            Select::make('kategori')
                ->label('Kategori')
                ->required()
                ->options([
                    'Aktiva Lancar'                  => 'Aktiva Lancar',
                    'Aktiva Tetap'                   => 'Aktiva Tetap',
                    'Aktiva Tetap (Kontra)'          => 'Aktiva Tetap (Kontra)',
                    'Kewajiban Lancar'               => 'Kewajiban Lancar',
                    'Modal'                          => 'Modal',
                    'Modal (Kontra)'                 => 'Modal (Kontra)',
                    'Pendapatan'                     => 'Pendapatan',
                    'Pendapatan (Kontra)'            => 'Pendapatan (Kontra)',
                    'Pendapatan Lain-Lain'           => 'Pendapatan Lain-Lain',
                    'Beban Pokok Penjualan'          => 'Beban Pokok Penjualan',
                    'Beban Pokok Penjualan (Kontra)' => 'Beban Pokok Penjualan (Kontra)',
                    'Beban Operasional'              => 'Beban Operasional',
                ])
                ->native(false),

            Select::make('saldo_normal')
                ->label('Saldo Normal')
                ->required()
                ->options([
                    'Debit'  => 'Debit',
                    'Kredit' => 'Kredit',
                ])
                ->native(false),
        ]);
    }
}
