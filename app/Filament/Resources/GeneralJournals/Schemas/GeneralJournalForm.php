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
                    \Filament\Forms\Components\DatePicker::make('tanggal')
                        ->label('Tanggal Jurnal')
                        ->required()
                        ->default(now())
                        ->native(false)
                        ->rule(function () {
                            return function (string $attribute, $value, \Closure $fail) {
                                $date = \Carbon\Carbon::parse($value);
                                $period = \App\Models\AccountingPeriod::where('start_date', '<=', $date->toDateString())
                                    ->where('end_date', '>=', $date->toDateString())
                                    ->first();
                                
                                if (!$period) {
                                    $fail('Tanggal ini tidak masuk ke dalam Periode Akuntansi manapun. Buat periode dulu.');
                                    return;
                                }
                                if ($period->isClosed()) {
                                    $fail("Periode akuntansi ({$period->label}) sudah CLOSED (Tutup Buku). Jurnal tidak bisa ditambah/diubah.");
                                }
                            };
                        }),

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
                        ->rule(function () {
                            return function (string $attribute, $value, \Closure $fail) {
                                $totalDebit = collect($value)->sum('debit');
                                $totalKredit = collect($value)->sum('kredit');
                                
                                if ($totalDebit !== $totalKredit) {
                                    $selisih = abs($totalDebit - $totalKredit);
                                    $fail("Jurnal tidak seimbang (Timpang). Total Debit: Rp " . number_format($totalDebit, 0, ',', '.') . " | Total Kredit: Rp " . number_format($totalKredit, 0, ',', '.') . " | Selisih: Rp " . number_format($selisih, 0, ',', '.'));
                                }
                            };
                        })
                        ->columnSpanFull(),

                    \Filament\Forms\Components\Placeholder::make('balance_indicator')
                        ->label('Status Keseimbangan (Live)')
                        ->content(function ($get) {
                            $items = $get('items') ?? [];
                            $totalDebit = collect($items)->sum('debit');
                            $totalKredit = collect($items)->sum('kredit');
                            $selisih = abs($totalDebit - $totalKredit);

                            if ($totalDebit === $totalKredit && $totalDebit > 0) {
                                return new \Illuminate\Support\HtmlString('<span style="color: green; font-weight: bold;">✅ SEIMBANG (Total: Rp ' . number_format($totalDebit, 0, ',', '.') . ')</span>');
                            }
                            return new \Illuminate\Support\HtmlString('<span style="color: red; font-weight: bold;">❌ TIMPANG (Selisih: Rp ' . number_format($selisih, 0, ',', '.') . ')</span>');
                        })
                        ->columnSpanFull(),
                ]),
        ]);
    }
}
