<?php

namespace App\Filament\Resources\Products\Tables;

use App\Filament\Resources\Products\ProductResource;
use App\Models\InvoiceItem;
use App\Models\StockMovement;
use App\Services\AccountingService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Support\Enums\Size;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // Default: hanya tampilkan produk aktif dan hitung stok yang dibooking
            ->modifyQueryUsing(function (Builder $query) {
                $query->where('is_active', true)
                    ->withSum(['invoiceItems as booked_stock' => function ($q) {
                        $q->whereHas('invoice', function ($q2) {
                            $q2->where('status', 'pending');
                        });
                    }], 'quantity');
            })
            ->defaultSort('sku', 'asc')
            ->columns([
                TextColumn::make('no')
                    ->label('NO.')
                    ->rowIndex(),
                TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight('bold')
                    ->color('primary')
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('nama')
                    ->label('NAMA')
                    ->searchable(
                        query: function ($query, string $search) {
                            return $query
                                ->where('nama', 'like', "%{$search}%")
                                ->orWhere('sku', 'like', "%{$search}%")
                                ->orWhere('harga_jual', $search);
                        }
                    )
                    ->sortable(),
                TextColumn::make('kategori')
                    ->label('KATEGORI')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'bunga' => 'BUNGA',
                        'packaging' => 'KEMASAN',
                        'others' => 'LAINNYA',
                        default => strtoupper($state),
                    })
                    ->color(fn ($state) => match ($state) {
                        'bunga' => 'flower',
                        'packaging' => 'package',
                        'others' => 'other',
                        default => 'gray',
                    })
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('harga_beli')
                    ->label('HARGA BELI')
                    ->formatStateUsing(fn ($state) => 'Rp '.number_format($state ?? 0, 0, ',', '.'))
                    ->sortable(),
                TextColumn::make('harga_jual')
                    ->label(function ($livewire) {
                        return match ($livewire->priceTier ?? 'toko') {
                            'vendor' => 'HARGA VENDOR',
                            'dekor' => 'HARGA DEKOR',
                            default => 'HARGA TOKO',
                        };
                    })
                    ->state(function ($record, $livewire) {
                        return match ($livewire->priceTier ?? 'toko') {
                            'vendor' => $record->harga_vendor,
                            'dekor' => $record->harga_dekor,
                            default => $record->harga_jual,
                        };
                    })
                    ->formatStateUsing(fn ($state) => 'Rp '.number_format($state ?? 0, 0, ',', '.'))
                    ->sortable(query: function (Builder $query, string $direction, $livewire) {
                        $column = match ($livewire->priceTier ?? 'toko') {
                            'vendor' => 'harga_vendor',
                            'dekor' => 'harga_dekor',
                            default => 'harga_jual',
                        };

                        return $query->orderBy($column, $direction);
                    }),
                TextColumn::make('margin')
                    ->label('MARGIN')
                    ->state(function ($record, $livewire) {
                        $sellingPrice = match ($livewire->priceTier ?? 'toko') {
                            'vendor' => $record->harga_vendor,
                            'dekor' => $record->harga_dekor,
                            default => $record->harga_jual,
                        };

                        return $sellingPrice - $record->harga_beli;
                    })
                    ->formatStateUsing(fn ($state) => 'Rp '.number_format($state ?? 0, 0, ',', '.'))
                    ->sortable(query: function (Builder $query, string $direction, $livewire) {
                        $column = match ($livewire->priceTier ?? 'toko') {
                            'vendor' => 'harga_vendor',
                            'dekor' => 'harga_dekor',
                            default => 'harga_jual',
                        };

                        // Sort by expression: (harga - harga_beli)
                        return $query->orderByRaw("({$column} - harga_beli) {$direction}");
                    })
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('margin_percent')
                    ->label('MARKUP')
                    ->state(function ($record, $livewire) {
                        if ($record->harga_beli <= 0) {
                            return 0;
                        }
                        $sellingPrice = match ($livewire->priceTier ?? 'toko') {
                            'vendor' => $record->harga_vendor,
                            'dekor' => $record->harga_dekor,
                            default => $record->harga_jual,
                        };

                        return round((($sellingPrice - $record->harga_beli) / $record->harga_beli) * 100);
                    })
                    ->sortable(query: function (Builder $query, string $direction, $livewire) {
                        $column = match ($livewire->priceTier ?? 'toko') {
                            'vendor' => 'harga_vendor',
                            'dekor' => 'harga_dekor',
                            default => 'harga_jual',
                        };

                        return $query->orderByRaw("(({$column} - harga_beli) / NULLIF(harga_beli, 0)) {$direction}");
                    })
                    ->suffix('%')
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('stok')
                    ->label('STOK FISIK')
                    ->sortable(),
                TextColumn::make('booked_stock')
                    ->label('DIPESAN')
                    ->state(fn ($record) => $record->booked_stock ?? 0)
                    ->color('warning'),
                TextColumn::make('available_stock')
                    ->label('STOK SISTEM')
                    ->state(fn ($record) => $record->stok - ($record->booked_stock ?? 0))
                    ->badge()
                    ->color(fn ($state) => match (true) {
                        $state <= 10 => 'danger',
                        $state <= 30 => 'warning',
                        default => 'success',
                    }),
                TextColumn::make('created_at')
                    ->label('DIBUAT PADA')
                    ->date('d M Y H:i')
                    ->description(fn ($record) => $record->updated_at->diffForHumans())
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('DIPERBARUI PADA')
                    ->date('d M Y H:i')
                    ->description(fn ($record) => $record->updated_at->diffForHumans())
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                // filter stok
                SelectFilter::make('stock_status')
                    ->label('Status Stok')
                    ->options([
                        'habis' => '🔴 Habis (0)',
                        'hampir_habis' => '🟠 Hampir Habis (1–10)',
                        'rendah' => '🟡 Rendah (11–30)',
                        'aman' => '🟢 Aman (>30)',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['value'] ?? null) {
                            'habis' => $query->where('stok', 0),
                            'hampir_habis' => $query->whereBetween('stok', [1, 10]),
                            'rendah' => $query->whereBetween('stok', [11, 30]),
                            'aman' => $query->where('stok', '>', 30),
                            default => $query,
                        };
                    })
                    ->native(false),

                // harga beli
                Filter::make('harga_beli')
                    ->label('Rentang Harga Beli')
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                        ])->schema([
                            TextInput::make('min_cost')
                                ->label('Harga Beli Min')
                                ->numeric()
                                ->step(1000)
                                ->minValue(0)
                                ->prefix('Rp')
                                ->placeholder('0'),
                            TextInput::make('max_cost')
                                ->label('Harga Beli Maks')
                                ->numeric()
                                ->step(1000)
                                ->minValue(0)
                                ->prefix('Rp')
                                ->placeholder('~'),
                        ]),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['min_cost'],
                                fn (Builder $query, $min): Builder => $query->where('harga_beli', '>=', $min)
                            )
                            ->when(
                                $data['max_cost'],
                                fn (Builder $query, $max): Builder => $query->where('harga_beli', '<=', $max)
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['min_cost'] ?? null) {
                            $indicators['min_cost'] = 'Harga Beli Min: Rp '.number_format($data['min_cost'], 0, ',', '.');
                        }
                        if ($data['max_cost'] ?? null) {
                            $indicators['max_cost'] = 'Harga Beli Maks: Rp '.number_format($data['max_cost'], 0, ',', '.');
                        }

                        return $indicators;
                    }),

                // harga jual
                Filter::make('harga_jual')
                    ->label('Rentang Harga Jual')
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                        ])->schema([
                            TextInput::make('min_price')
                                ->label('Harga Jual Min')
                                ->numeric()
                                ->step(1000)
                                ->minValue(0)
                                ->prefix('Rp')
                                ->placeholder('0'),
                            TextInput::make('max_price')
                                ->label('Harga Jual Maks')
                                ->numeric()
                                ->step(1000)
                                ->minValue(0)
                                ->prefix('Rp')
                                ->placeholder('~'),
                        ]),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['min_price'],
                                fn (Builder $query, $min): Builder => $query->where('harga_jual', '>=', $min)
                            )
                            ->when(
                                $data['max_price'],
                                fn (Builder $query, $max): Builder => $query->where('harga_jual', '<=', $max)
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['min_price'] ?? null) {
                            $indicators['min_price'] = 'Harga Jual Min: Rp '.number_format($data['min_price'], 0, ',', '.');
                        }
                        if ($data['max_price'] ?? null) {
                            $indicators['max_price'] = 'Harga Jual Maks: Rp '.number_format($data['max_price'], 0, ',', '.');
                        }

                        return $indicators;
                    }),
            ])
            ->filtersFormWidth('md')
            ->filtersTriggerAction(
                fn (Action $action) => $action
                    ->button()
                    ->label('Filter')
                    ->icon('heroicon-m-funnel')
                    ->size(Size::Small)
            )
            ->recordActionsColumnLabel('AKSI')
            ->recordActions([
                Action::make('adjustStock')
                    ->hiddenLabel()
                    ->icon('heroicon-o-arrow-path')
                    ->size(Size::Small)
                    ->color('info')
                    ->tooltip('Edit Stok')
                    ->modalHeading(fn ($record) => 'Edit Stok: '.$record->nama)
                    ->modalDescription(function () {
                        $today = now()->toDateString();
                        $period = \App\Models\AccountingPeriod::where('start_date', '<=', $today)
                            ->where('end_date', '>=', $today)
                            ->first();

                        if ($period && $period->isClosed()) {
                            return "⚠️ PERINGATAN: Periode akuntansi '{$period->label}' saat ini berstatus DITUTUP (Tutup Buku). Penyesuaian stok tidak dapat diproses sampai periode dibuka kembali.";
                        }

                        return null;
                    })
                    ->modalSubmitActionLabel('Simpan')
                    ->modalCancelActionLabel('Batal')
                    ->form([
                        Select::make('type')
                            ->label('Jenis Transaksi')
                            ->options([
                                'in' => 'Stok Masuk (Masuk / Koreksi)',
                                'out' => 'Stok Keluar (Rusak / Hilang)',
                            ])
                            ->required()
                            ->native(false),
                        TextInput::make('quantity')
                            ->label('Jumlah')
                            ->required()
                            ->numeric()
                            ->minValue(1)
                            ->maxLength(10)
                            ->extraInputAttributes([
                                'inputmode' => 'numeric',
                                'min' => 1,
                                'oninput' => "this.value=this.value.replace(/[^0-9]/g,'').replace(/^0+(?=\\d)/,'');let v=this.value;this.value=v.replace(/\\B(?=(\\d{3})+(?!\\d))/g,'.');",
                            ])
                            ->dehydrateStateUsing(fn ($state) => (int) str_replace('.', '', (string) ($state ?? 0))),
                        Textarea::make('notes')
                            ->label('Catatan Alasan')
                            ->required()
                            ->placeholder('Misal: 2 bunga layu tidak layak jual')
                            ->rows(3),
                    ])
                    ->action(function (array $data, $record) {
                        $qty = (int) $data['quantity'];
                        $type = $data['type'];

                        if ($type === 'out' && $record->stok < $qty) {
                            Notification::make()
                                ->title('Stok Tidak Cukup')
                                ->body("Tidak bisa mengurangi stok sebanyak {$qty}. Stok saat ini hanya {$record->stok}.")
                                ->danger()
                                ->send();

                            return;
                        }

                        $today = now()->toDateString();
                        $period = \App\Models\AccountingPeriod::where('start_date', '<=', $today)
                            ->where('end_date', '>=', $today)
                            ->first();

                        if ($period && $period->isClosed()) {
                            Notification::make()
                                ->title('Periode Akuntansi Sudah Ditutup')
                                ->body("Periode '{$period->label}' saat ini berstatus Ditutup (Tutup Buku). Penyesuaian stok yang menghasilkan jurnal keuangan tidak dapat dilakukan.")
                                ->danger()
                                ->persistent()
                                ->send();

                            return;
                        }

                        try {
                            DB::transaction(function () use ($data, $record, $qty, $type) {
                                StockMovement::create([
                                    'product_id' => $record->id,
                                    'type' => $type,
                                    'quantity' => $qty,
                                    'notes' => 'Manual Adj: '.$data['notes'],
                                    'user_id' => Filament::auth()->id(),
                                    'reference_id' => 'ADJ-'.time(),
                                ]);

                                if ($type === 'in') {
                                    $record->increment('stok', $qty);
                                } else {
                                    $record->decrement('stok', $qty);
                                }

                                // Auto-create accounting journal for stock adjustment
                                app(AccountingService::class)->createStockAdjustmentJournal(
                                    product: $record,
                                    quantity: $qty,
                                    type: $type,
                                    notes: $data['notes'] ?? ''
                                );
                            });

                            Notification::make()
                                ->success()
                                ->title('Stok Diperbarui')
                                ->body("Stok {$record->nama} telah disesuaikan.")
                                ->send();
                        } catch (\App\Exceptions\AccountingPeriodClosedException $e) {
                            Notification::make()
                                ->title('Periode Akuntansi Sudah Ditutup')
                                ->body($e->getMessage())
                                ->danger()
                                ->persistent()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Gagal Memperbarui Stok')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                ActionGroup::make([
                    EditAction::make()
                        ->hiddenLabel()
                        ->authorize(fn () => true)
                        ->visible(fn () => true)
                        ->disabled(fn ($record) => Gate::denies('update', $record))
                        ->tooltip(function ($record) {
                            $response = Gate::inspect('update', $record);

                            return $response->denied() ? $response->message() : '';
                        })
                        ->icon(function ($record) {
                            return Gate::inspect('update', $record)->denied()
                                ? 'heroicon-o-lock-closed'
                                : 'heroicon-o-pencil-square';
                        })
                        ->color(function ($record) {
                            return Gate::inspect('update', $record)->denied()
                                ? 'gray'
                                : 'primary';
                        })
                        ->before(function ($action, $record) {
                            $response = Gate::inspect('update', $record);

                            if ($response->denied()) {
                                Notification::make()
                                    ->warning()
                                    ->title('Akses Ditolak')
                                    ->body($response->message())
                                    ->send();

                                $action->halt();
                            }
                        }),

                    // === SOFT DELETE: set is_active = false instead of real delete ===
                    Action::make('deactivate')
                        ->label('Nonaktifkan')
                        ->hiddenLabel()
                        ->authorize(fn () => true)
                        ->visible(fn () => true)
                        ->disabled(fn ($record) => Gate::denies('delete', $record))
                        ->requiresConfirmation()
                        ->modalHeading(function ($record) {
                            $invoiceCount = InvoiceItem::where('product_id', $record->id)
                                ->distinct('invoice_id')
                                ->count('invoice_id');

                            return $invoiceCount > 0
                                ? "⚠️ Hapus Produk (Terdapat di {$invoiceCount} Invoice)"
                                : 'Hapus Produk';
                        })
                        ->modalDescription(function ($record) {
                            $invoiceCount = InvoiceItem::where('product_id', $record->id)
                                ->distinct('invoice_id')
                                ->count('invoice_id');
                            if ($invoiceCount > 0) {
                                return 'Produk "'.$record->nama."\" (SKU: {$record->sku}) tercatat pada {$invoiceCount} invoice. Produk akan dinonaktifkan dan tidak tampil di daftar produk maupun dropdown invoice, namun data transaksi historis tetap aman. Lanjutkan?";
                            }

                            return 'Produk akan dinonaktifkan dan tidak tampil di daftar. Data transaksi historis tetap aman. Lanjutkan?';
                        })
                        ->modalSubmitActionLabel('Ya, Hapus')
                        ->modalCancelActionLabel('Batal')
                        ->tooltip(function ($record) {
                            $response = Gate::inspect('delete', $record);

                            return $response->denied() ? $response->message() : '';
                        })
                        ->icon(function ($record) {
                            return Gate::inspect('delete', $record)->denied()
                                ? 'heroicon-o-lock-closed'
                                : 'heroicon-o-trash';
                        })
                        ->color(function ($record) {
                            return Gate::inspect('delete', $record)->denied()
                                ? 'gray'
                                : 'danger';
                        })
                        ->action(function ($record) {
                            $record->update(['is_active' => false]);

                            Notification::make()
                                ->success()
                                ->title('Produk Dihapus')
                                ->body("Produk \"{$record->nama}\" telah dinonaktifkan.")
                                ->send();
                        })
                        ->before(function ($action, $record) {
                            $response = Gate::inspect('delete', $record);

                            if ($response->denied()) {
                                Notification::make()
                                    ->danger()
                                    ->title('Akses Ditolak')
                                    ->body($response->message())
                                    ->send();

                                $action->halt();
                            }
                        }),
                ])
                    ->icon('heroicon-m-ellipsis-horizontal')
                    ->color('gray')
                    ->size(Size::Small)
                    ->tooltip('Aksi Lainnya'),
            ])
            ->toolbarActions([
                Action::make('priceTierSelector')
                    ->view('filament.products.price-tier-selector'),
                BulkActionGroup::make([
                    // === SOFT DELETE BULK: set is_active = false ===
                    BulkAction::make('deactivateBulk')
                        ->label('Hapus')
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading(function (Collection $records) {
                            $affectedIds = $records->pluck('id');
                            $invoiceCount = InvoiceItem::whereIn('product_id', $affectedIds)
                                ->distinct('invoice_id')
                                ->count('invoice_id');

                            return $invoiceCount > 0
                                ? "⚠️ Hapus Produk Terpilih (Mempengaruhi {$invoiceCount} Invoice)"
                                : 'Hapus Produk Terpilih';
                        })
                        ->modalDescription(function (Collection $records) {
                            $affectedIds = $records->pluck('id');
                            $invoiceCount = InvoiceItem::whereIn('product_id', $affectedIds)
                                ->distinct('invoice_id')
                                ->count('invoice_id');
                            if ($invoiceCount > 0) {
                                $affected = $records->filter(fn ($r) => InvoiceItem::where('product_id', $r->id)->exists()
                                )->pluck('nama')->implode(', ');

                                return "Beberapa produk yang dipilih ({$affected}) tercatat pada {$invoiceCount} invoice. Produk akan dinonaktifkan namun data transaksi historis tetap aman. Lanjutkan?";
                            }

                            return 'Produk terpilih akan dinonaktifkan. Data transaksi historis tetap aman. Lanjutkan?';
                        })
                        ->modalSubmitActionLabel('Ya, Hapus Semua')
                        ->modalCancelActionLabel('Batal')
                        ->visible(fn () => Filament::auth()->user()?->is_super_admin)
                        ->action(function (Collection $records) {
                            $count = 0;
                            foreach ($records as $record) {
                                $record->update(['is_active' => false]);
                                $count++;
                            }

                            Notification::make()
                                ->success()
                                ->title('Produk Dihapus')
                                ->body("{$count} produk berhasil dinonaktifkan.")
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->recordUrl(function ($record) {
                return ProductResource::getUrl('edit', ['record' => $record]);
            });
    }
}
