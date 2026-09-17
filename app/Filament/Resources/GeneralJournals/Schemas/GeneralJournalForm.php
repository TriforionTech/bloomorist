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
                ->columns(2)
                ->columnSpanFull()
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
                        ->label('Nomor Bukti')
                        ->required()
                        ->disabled(fn ($operation) => $operation === 'edit')
                        ->dehydrated()
                        ->default(fn () => app(AccountingService::class)->generateNoBukti('JU'))
                        ->unique(ignoreRecord: true)
                        ->helperText('Otomatis dibuat oleh sistem (Unik).')
                        ->columnSpan(1),

                    TextInput::make('keterangan')
                        ->label('Keterangan Jurnal')
                        ->required()
                        ->maxLength(255)
                        ->columnSpan(1),
                ]),

            Section::make('Detail Jurnal')
                ->columnSpanFull()
                ->schema([
                    Repeater::make('items')
                        ->label('')
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
                                ->columnSpan([
                                    'default' => 1,
                                    'md' => 2,
                                ]),

                            \Filament\Forms\Components\Hidden::make('kode_coa'),

                            TextInput::make('debit')
                                ->label('Debit')
                                ->numeric()
                                ->default(0)
                                ->minValue(0)
                                ->live(onBlur: true)
                                ->prefix('Rp')
                                ->columnSpan([
                                    'default' => 1,
                                    'md' => 1,
                                ]),

                            TextInput::make('kredit')
                                ->label('Kredit')
                                ->numeric()
                                ->default(0)
                                ->minValue(0)
                                ->live(onBlur: true)
                                ->prefix('Rp')
                                ->columnSpan([
                                    'default' => 1,
                                    'md' => 1,
                                ]),
                        ])
                        ->columns([
                            'default' => 1,
                            'md' => 4,
                        ])
                        ->minItems(2)
                        ->defaultItems(2)
                        ->addActionLabel('Tambah Baris Akun')
                        ->reorderable(false)
                        ->rule(function () {
                            return function (string $attribute, $value, \Closure $fail) {
                                $totalDebit = collect($value)->sum('debit');
                                $totalKredit = collect($value)->sum('kredit');
                                
                                if ($totalDebit !== $totalKredit) {
                                    $selisih = abs($totalDebit - $totalKredit);
                                    $fail("Jurnal belum seimbang (selisih Rp " . number_format($selisih, 0, ',', '.') . ").");
                                }
                                if ($totalDebit == 0) {
                                    $fail("Nominal jurnal tidak boleh 0.");
                                }
                            };
                        })
                        ->columnSpanFull(),
                ]),
        ]);
    }
}
