<?php

namespace App\Filament\Resources\Expenses\Schemas;

use App\Models\ChartOfAccount;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ExpenseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('keterangan')
                ->label('Keterangan')
                ->required()
                ->maxLength(255)
                ->placeholder('Keterangan pengeluaran')
                ->columnSpanFull(),

            TextInput::make('nominal')
                ->label('Nominal')
                ->required()
                ->minValue(1)
                ->prefix('Rp')
                ->placeholder('0')
                ->maxLength(13)
                ->extraInputAttributes([
                    'inputmode' => 'numeric',
                    'oninput' => "this.value=this.value.replace(/[^0-9]/g,'').replace(/^0+(?=\\d)/,'');let v=this.value;this.value=v.replace(/\\B(?=(\\d{3})+(?!\\d))/g,'.');",
                ])
                ->dehydrateStateUsing(fn ($state) => (int) str_replace('.', '', (string) ($state ?? 0)))
                ->formatStateUsing(fn ($state) => $state ? number_format((int) $state, 0, ',', '.') : ''),

            Select::make('coa_id')
                ->label('Akun Beban (Debit)')
                ->required()
                ->options(
                    ChartOfAccount::where('kategori', 'Beban')
                        ->orderBy('kode_akun')
                        ->get()
                        ->mapWithKeys(fn ($coa) => [
                            $coa->id => "{$coa->kode_akun} — {$coa->nama_akun}",
                        ])
                )
                ->searchable()
                ->native(false)
                ->helperText('Pilih kategori beban untuk pengeluaran ini'),

            Select::make('coa_kredit_id')
                ->label('Akun Kas/Bank (Kredit)')
                ->required()
                ->options(
                    ChartOfAccount::whereIn('kategori', ['Aset'])
                        ->orderBy('kode_akun')
                        ->get()
                        ->mapWithKeys(fn ($coa) => [
                            $coa->id => "{$coa->kode_akun} — {$coa->nama_akun}",
                        ])
                )
                ->searchable()
                ->native(false)
                ->helperText('Pilih akun kas/bank yang digunakan untuk membayar'),
        ]);
    }
}
