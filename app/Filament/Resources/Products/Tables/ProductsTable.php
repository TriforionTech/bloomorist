<?php

namespace App\Filament\Resources\Products\Tables;

use App\Filament\Resources\Products\ProductResource;
use App\Models\StockMovement;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
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
            ->columns([
                TextColumn::make('no')
                    ->label('NO.')
                    ->rowIndex(),
                TextColumn::make('nama_barang')
                    ->label('NAME')
                    ->searchable(
                        query: function ($query, string $search) {
                            return $query
                                ->where('nama_barang', 'like', "%{$search}%")
                                ->orWhere('harga_jual_barang', $search);
                        }
                    )
                    ->sortable(),
                TextColumn::make('harga_beli_barang')
                    ->label('COST PRICE')
                    ->formatStateUsing(fn ($state) => 'Rp ' . number_format($state ?? 0, 0, ',', '.'))
                    ->sortable(),
                TextColumn::make('harga_jual_barang')
                    ->label('SELLING PRICE')
                    ->formatStateUsing(fn ($state) => 'Rp ' . number_format($state ?? 0, 0, ',', '.'))
                    ->sortable(),
                TextColumn::make('margin')
                    ->label('MARGIN')
                    ->state(function ($record) {
                        return $record->harga_jual_barang - $record->harga_beli_barang;
                    })
                    ->formatStateUsing(fn ($state) => 'Rp ' . number_format($state ?? 0, 0, ',', '.'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('margin_percent')
                    ->label('MARKUP')
                    ->sortable()
                    ->state(function ($record) {
                        if ($record->harga_beli_barang <= 0) {
                            return 0;
                        }

                        return round(
                            (($record->harga_jual_barang - $record->harga_beli_barang)
                            / $record->harga_beli_barang) * 100
                        );
                    })
                    ->suffix('%')
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('stok_barang')
                    ->label('STOCK')
                    ->badge()
                    ->color(fn ($state) => match (true) {
                        $state <= 10 => 'danger',
                        $state <= 30 => 'warning',
                        default => 'success',
                    })
                    ->sortable(),
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
                Filter::make('harga_beli_barang')
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
                                fn (Builder $query, $min): Builder => $query->where('harga_beli_barang', '>=', $min)
                            )
                            ->when(
                                $data['max_cost'],
                                fn (Builder $query, $max): Builder => $query->where('harga_beli_barang', '<=', $max)
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
                Filter::make('harga_jual_barang')
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
                                fn (Builder $query, $min): Builder => $query->where('harga_jual_barang', '>=', $min)
                            )
                            ->when(
                                $data['max_price'],
                                fn (Builder $query, $max): Builder => $query->where('harga_jual_barang', '<=', $max)
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
                    ->modalHeading(fn ($record) => 'Adjust Stock: ' . $record->nama_barang)
                    ->modalSubmitActionLabel('Simpan')
                    ->modalCancelActionLabel('Batal')
                    ->form([
                        Select::make('type')
                            ->label('Jenis Transaksi')
                            ->options([
                                'in' => 'Stock In',
                                'out' => 'Stock Out',
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
                            ->label('Catatan')
                            ->required()
                            ->placeholder('Catatan untuk stock adjustment')
                            ->rows(3),
                    ])
                    ->action(function (array $data, $record) {
                        DB::transaction(function () use ($data, $record) {
                            StockMovement::create([
                                'product_id' => $record->id,
                                'type' => $data['type'],
                                'quantity' => $data['quantity'],
                                'notes' => $data['notes'],
                                'user_id' => Filament::auth()->id(),
                            ]);

                            if ($data['type'] === 'in') {
                                $record->increment('stok_barang', $data['quantity']);
                            } else {
                                $record->decrement('stok_barang', $data['quantity']);
                            }
                        });

                        Notification::make()
                            ->success()
                            ->title('Stock Updated')
                            ->body("Stock {$data['type']} of {$data['quantity']} unit(s) has been recorded.")
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
                    DeleteAction::make()
                    ->hiddenLabel()
                    ->size('xl')
                    ->authorize(fn () => true)
                    ->visible(fn () => true)
                    ->disabled(fn ($record) => Gate::denies('delete', $record))
                    ->modalHeading('Hapus Produk')
                    ->modalDescription('Apakah Anda yakin ingin menghapus produk ini? Tindakan ini tidak dapat dibatalkan.')
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
                DeleteBulkAction::make()
                    ->modalHeading('Hapus Produk Terpilih')
                    ->modalDescription('Apakah Anda yakin ingin menghapus semua produk yang dipilih? Tindakan ini tidak dapat dibatalkan.')
                    ->modalSubmitActionLabel('Ya, Hapus Semua')
                    ->modalCancelActionLabel('Batal')
                    ->visible(fn () => Filament::auth()->user()?->is_super_admin)
                    ->before(function ($action) {
                        $currentUser = Filament::auth()->user();
                        
                        if (!$currentUser?->is_super_admin) {
                            Notification::make()
                                ->danger()
                                ->title('Akses Ditolak')
                                ->body('Hanya Superadmin yang dapat menghapus data produk.')
                                ->send();
                            
                            $action->halt();
                            return;
                        }
                    })
                    ->action(function (Collection $records) {
                        $deletedCount = 0;
                        
                        foreach ($records as $record) {
                            $record->delete();
                            $deletedCount++;
                        }
                        
                        Notification::make()
                            ->success()
                            ->title('Produk Dihapus')
                            ->body("{$deletedCount} produk berhasil dihapus dari sistem.")
                            ->send();
                    }),
            ]),
        ])
        
        ->recordUrl(function ($record) {
            // return Filament::auth()->user()?->is_super_admin 
            //     ? ProductResource::getUrl('edit', ['record' => $record]) 
            //     : null;
            return ProductResource::getUrl('edit', ['record' => $record]);
        });
    }
}
