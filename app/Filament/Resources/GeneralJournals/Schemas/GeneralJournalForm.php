<?php

namespace App\Filament\Resources\GeneralJournals\Schemas;

use App\Models\ChartOfAccount;
use App\Services\AccountingService;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Validation\ValidationException;

class GeneralJournalForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Header Jurnal')
                ->schema([
                    TextInput::make('no_bukti')
                        ->label('No. Bukti')
                        ->required()
                        ->disabled(fn ($operation) => $operation === 'edit')
                        ->dehydrated()
                        ->default(fn () => app(AccountingService::class)->generateNoBukti('JU'))
                        ->unique(ignoreRecord: true),

                    TextInput::make('keterangan')
                        ->label('Keterangan')
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull(),
                ]),

            Section::make('Detail Jurnal')
                ->schema([
                    Repeater::make('items')
                        ->label('Baris Jurnal')
                        ->relationship()
                        ->schema([
                            Select::make('coa_id')
                                ->label('Akun')
                                ->options(
                                    ChartOfAccount::orderBy('kode_akun')
                                        ->get()
                                        ->mapWithKeys(fn ($coa) => [
                                            $coa->id => "{$coa->kode_akun} — {$coa->nama_akun}",
                                        ])
                                )
                                ->required()
                                ->searchable()
                                ->native(false)
                                ->live()
                                ->afterStateUpdated(function ($state, Set $set) {
                                    if ($state) {
                                        $coa = ChartOfAccount::find($state);
                                        $set('kode_coa', $coa?->kode_akun ?? '');
                                    }
                                })
                                ->columnSpan(2),

                            TextInput::make('kode_coa')
                                ->label('Kode')
                                ->disabled()
                                ->dehydrated()
                                ->columnSpan(1),

                            TextInput::make('debit')
                                ->label('Debit')
                                ->numeric()
                                ->default(0)
                                ->minValue(0)
                                ->live(onBlur: true)
                                ->prefix('Rp')
                                ->columnSpan(1),

                            TextInput::make('kredit')
                                ->label('Kredit')
                                ->numeric()
                                ->default(0)
                                ->minValue(0)
                                ->live(onBlur: true)
                                ->prefix('Rp')
                                ->columnSpan(1),
                        ])
                        ->columns(5)
                        ->minItems(2)
                        ->defaultItems(2)
                        ->addActionLabel('+ Tambah Baris')
                        ->reorderable(false)
                        ->columnSpanFull(),
                ]),
        ]);
    }
}
