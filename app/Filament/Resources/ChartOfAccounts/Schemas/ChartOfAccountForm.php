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
                    'Aset'       => 'Aset',
                    'Kewajiban'  => 'Kewajiban',
                    'Ekuitas'    => 'Ekuitas',
                    'Pendapatan' => 'Pendapatan',
                    'Beban'      => 'Beban',
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
