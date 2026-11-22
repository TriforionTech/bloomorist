<?php

namespace App\Filament\Resources\AccountingPeriods\Schemas;

use App\Models\AccountingPeriod;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AccountingPeriodForm
{
    public static function configure(Schema $schema): Schema
    {
        $monthNames = [
            '01' => 'Januari',
            '02' => 'Februari',
            '03' => 'Maret',
            '04' => 'April',
            '05' => 'Mei',
            '06' => 'Juni',
            '07' => 'Juli',
            '08' => 'Agustus',
            '09' => 'September',
            '10' => 'Oktober',
            '11' => 'November',
            '12' => 'Desember',
        ];

        $syncPeriod = function (callable $get, callable $set) use ($monthNames) {
            $month = $get('month');
            $year = $get('year');

            if ($month && $year) {
                $monthKey = str_pad((string) $month, 2, '0', STR_PAD_LEFT);
                $date = Carbon::createFromDate((int) $year, (int) $month, 1);
                $monthLabel = $monthNames[$monthKey] ?? $date->translatedFormat('F');

                $set('label', "{$monthLabel} {$year}");
                $set('start_date', $date->copy()->startOfMonth()->toDateString());
                $set('end_date', $date->copy()->endOfMonth()->toDateString());
            }
        };

        return $schema
            ->schema([
                Section::make('Pilihan Bulan & Kalender Periode')
                    ->description('Pilih bulan dan tahun kalender. Sistem akan otomatis menetapkan tanggal 1 hingga akhir bulan.')
                    ->schema([
                        Select::make('month')
                            ->label('Bulan')
                            ->options($monthNames)
                            ->default(now()->format('m'))
                            ->searchable()
                            ->native(false)
                            ->live()
                            ->disabled(fn (?AccountingPeriod $record) => $record?->isClosed() ?? false)
                            ->afterStateHydrated(function (Select $component, ?AccountingPeriod $record) {
                                if ($record && $record->start_date) {
                                    $component->state(Carbon::parse($record->start_date)->format('m'));
                                }
                            })
                            ->afterStateUpdated($syncPeriod)
                            ->required()
                            ->rules([
                                function (callable $get, ?AccountingPeriod $record) {
                                    return function (string $attribute, $value, \Closure $fail) use ($get, $record) {
                                        $month = $value;
                                        $year = $get('year') ?? now()->year;
                                        if ($month && $year) {
                                            $startDate = Carbon::createFromDate((int) $year, (int) $month, 1)->startOfMonth()->toDateString();
                                            $exists = AccountingPeriod::whereDate('start_date', $startDate)
                                                ->when($record, fn ($q) => $q->where('id', '!=', $record->id))
                                                ->exists();
                                            if ($exists) {
                                                $fail('Periode akuntansi untuk bulan dan tahun ini sudah ada.');
                                            }
                                        }
                                    };
                                },
                            ]),

                        Select::make('year')
                            ->label('Tahun')
                            ->options(function () {
                                $currentYear = (int) now()->year;
                                $years = [];
                                for ($y = $currentYear - 3; $y <= $currentYear + 5; $y++) {
                                    $years[(string) $y] = (string) $y;
                                }

                                return $years;
                            })
                            ->default((string) now()->year)
                            ->searchable()
                            ->native(false)
                            ->live()
                            ->disabled(fn (?AccountingPeriod $record) => $record?->isClosed() ?? false)
                            ->afterStateHydrated(function (Select $component, ?AccountingPeriod $record) {
                                if ($record && $record->start_date) {
                                    $component->state(Carbon::parse($record->start_date)->format('Y'));
                                }
                            })
                            ->afterStateUpdated($syncPeriod)
                            ->required(),

                        TextInput::make('label')
                            ->label('Nama Periode')
                            ->required()
                            ->readOnly()
                            ->dehydrated()
                            ->default(function () use ($monthNames) {
                                $m = now()->format('m');

                                return ($monthNames[$m] ?? now()->format('F')).' '.now()->year;
                            })
                            ->helperText('Dihasilkan otomatis berdasarkan bulan & tahun terpilih.')
                            ->columnSpanFull(),

                        DatePicker::make('start_date')
                            ->label('Tanggal Mulai')
                            ->required()
                            ->readOnly()
                            ->dehydrated()
                            ->displayFormat('d/m/Y')
                            ->native(false)
                            ->default(now()->startOfMonth()->toDateString())
                            ->helperText('Otomatis tanggal 1 pada bulan terpilih.'),

                        DatePicker::make('end_date')
                            ->label('Tanggal Selesai')
                            ->required()
                            ->readOnly()
                            ->dehydrated()
                            ->displayFormat('d/m/Y')
                            ->native(false)
                            ->default(now()->endOfMonth()->toDateString())
                            ->helperText('Otomatis tanggal terakhir pada bulan terpilih.'),
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
                            ->placeholder('Otomatis dihitung saat Tutup Buku (opsional isi manual)')
                            ->helperText('Jika dikosongkan, sistem akan otomatis menghitung dari total fisik stok gudang saat Tutup Buku.')
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
