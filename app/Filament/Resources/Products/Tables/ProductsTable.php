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
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Filament\Support\Enums\Size;

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
                    ->label('NAME')
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
                    ->label('CATEGORY')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'bunga'     => 'FLOWERS',
                        'packaging' => 'PACKAGING',
                        'others'    => 'OTHERS',
                        default     => strtoupper($state),
                    })
                    ->color(fn ($state) => match ($state) {
                        'bunga'     => 'flower',
                        'packaging' => 'package',
                        'others'    => 'other',
                        default     => 'gray',
                    })
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('harga_beli')
                    ->label('COST PRICE')
                    ->formatStateUsing(fn ($state) => 'Rp ' . number_format($state ?? 0, 0, ',', '.'))
                    ->sortable(),
                TextColumn::make('harga_jual')
                    ->label('SELLING PRICE')
                    ->formatStateUsing(fn ($state) => 'Rp ' . number_format($state ?? 0, 0, ',', '.'))
                    ->sortable(),
                TextColumn::make('margin')
                    ->label('MARGIN')
                    ->state(function ($record) {
                        return $record->harga_jual - $record->harga_beli;
                    })
                    ->formatStateUsing(fn ($state) => 'Rp ' . number_format($state ?? 0, 0, ',', '.'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('margin_percent')
                    ->label('MARKUP')
                    ->sortable()
                    ->state(function ($record) {
                        if ($record->harga_beli <= 0) {
                            return 0;
                        }

                        return round(
                            (($record->harga_jual - $record->harga_beli)
                            / $record->harga_beli) * 100
                        );
                    })
                    ->suffix('%')
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('stok')
                    ->label('REAL STOCK')
                    ->sortable(),
                TextColumn::make('booked_stock')
                    ->label('BOOKED')
                    ->state(fn ($record) => $record->booked_stock ?? 0)
                    ->color('warning'),
                TextColumn::make('available_stock')
                    ->label('AVAILABLE')
                    ->state(fn ($record) => $record->stok - ($record->booked_stock ?? 0))
                    ->badge()
                    ->color(fn ($state) => match (true) {
                        $state <= 10 => 'danger',
                        $state <= 30 => 'warning',
                        default => 'success',
                    }),
                TextColumn::make('created_at')
                    ->label('CREATED AT')
                    ->date('d M Y H:i')
                    ->description(fn ($record) => $record->updated_at->diffForHumans())
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('UPDATED AT')
                    ->date('d M Y H:i')
                    ->description(fn ($record) => $record->updated_at->diffForHumans())
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                // harga beli
                Filter::make('harga_beli')
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                        ])->schema([
                            TextInput::make('min_cost')
                                ->label('Min Cost')
                                ->numeric()
                                ->step(1000)
                                ->minValue(0)
                                ->prefix('Rp')
                                ->placeholder('0'),
                            TextInput::make('max_cost')
                                ->label('Max Cost')
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
                            $indicators['min_cost'] = 'Cost Min: Rp ' . number_format($data['min_cost'], 0, ',', '.');
                        }
                        if ($data['max_cost'] ?? null) {
                            $indicators['max_cost'] = 'Cost Max: Rp ' . number_format($data['max_cost'], 0, ',', '.');
                        }
                        return $indicators;
                    }),

                // harga jual
                Filter::make('harga_jual')
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                        ])->schema([
                            TextInput::make('min_price')
                                ->label('Min Selling Price')
                                ->numeric()
                                ->step(1000)
                                ->minValue(0)
                                ->prefix('Rp')
                                ->placeholder('0'),
                            TextInput::make('max_price')
                                ->label('Max Selling Price')
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
                            $indicators['min_price'] = 'Sell Min: Rp ' . number_format($data['min_price'], 0, ',', '.');
                        }
                        if ($data['max_price'] ?? null) {
                            $indicators['max_price'] = 'Sell Max: Rp ' . number_format($data['max_price'], 0, ',', '.');
                        }
                        return $indicators;
                    }),

                // filter stok
                SelectFilter::make('stock_status')
                    ->label('Stock Status')
                    ->options([
                        'habis'        => '🔴 Habis (0)',
                        'hampir_habis' => '🟠 Hampir Habis (1–10)',
                        'rendah'       => '🟡 Rendah (11–30)',
                        'aman'         => '🟢 Aman (>30)',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['value'] ?? null) {
                            'habis'        => $query->where('stok', 0),
                            'hampir_habis' => $query->whereBetween('stok', [1, 10]),
                            'rendah'       => $query->whereBetween('stok', [11, 30]),
                            'aman'         => $query->where('stok', '>', 30),
                            default        => $query,
                        };
                    })
                    ->native(false),
            ])
            ->filtersFormWidth('xl')
            ->recordActionsColumnLabel('ACTIONS')
            ->recordActions([                
                Action::make('adjustStock')
                    ->hiddenLabel()
                    ->icon('heroicon-o-arrow-path')
                    ->size('xl')
                    ->color('info')
                    ->tooltip('Adjust Stock')
                    ->modalHeading(fn ($record) => 'Adjust Stock: ' . $record->nama)
                    ->modalSubmitActionLabel('Simpan')
                    ->modalCancelActionLabel('Batal')
                    ->form([
                        Select::make('type')
                            ->label('Jenis Transaksi')
                            ->options([
                                'in' => 'Stock In (Masuk/Koreksi)',
                                'out' => 'Stock Out (Rusak/Hilang)',
                            ])
                            ->required()
                            ->native(false),
                        TextInput::make('quantity')
                            ->label('Quantity')
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

                        DB::transaction(function () use ($data, $record, $qty, $type) {
                            StockMovement::create([
                                'product_id' => $record->id,
                                'type' => $type,
                                'quantity' => $qty,
                                'notes' => 'Manual Adj: ' . $data['notes'],
                                'user_id' => Filament::auth()->id(),
                                'reference_id' => 'ADJ-' . time(),
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
                            ->title('Stock Updated')
                            ->body("Stok {$record->nama} telah disesuaikan.")
                            ->send();
                    }),
                ActionGroup::make([
                    EditAction::make()
                    ->hiddenLabel()
                    ->size('xl')
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
                                ->title('Access Denied')
                                ->body($response->message())
                                ->send();
                            
                            $action->halt();
                        }
                    }),

                    // === SOFT DELETE: set is_active = false instead of real delete ===
                    Action::make('deactivate')
                    ->hiddenLabel()
                    ->size('xl')
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
                            return "Produk \"" . $record->nama . "\" (SKU: {$record->sku}) tercatat pada {$invoiceCount} invoice. Produk akan dinonaktifkan dan tidak tampil di daftar produk maupun dropdown invoice, namun data transaksi historis tetap aman. Lanjutkan?";
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
                ->tooltip('More Actions'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    // === SOFT DELETE BULK: set is_active = false ===
                    BulkAction::make('deactivateBulk')
                        ->label('Delete')
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
                                $affected = $records->filter(fn ($r) =>
                                    InvoiceItem::where('product_id', $r->id)->exists()
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
