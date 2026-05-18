<?php

namespace App\Filament\Resources\Products\Tables;

use App\Filament\Resources\Products\ProductResource;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

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
                    ->searchable()
                    ->sortable(),
                TextColumn::make('harga_beli_barang')
                    ->label('COST PRICE')
                    ->money('IDR', locale: 'id')
                    ->sortable(),
                TextColumn::make('harga_jual_barang')
                    ->label('SELLING PRICE')
                    ->money('IDR', locale: 'id')
                    ->sortable(),
                TextColumn::make('stok_barang')
                    ->label('STOK')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('CREATED AT')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('UPDATED AT')
                    ->dateTime()
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
                EditAction::make()
                    ->authorize(fn () => true)
                    ->visible(fn () => true)
                    ->disabled(fn ($record) => Gate::denies('update', $record))
                    ->tooltip(function ($record) {
                        $response = Gate::inspect('update', $record);
                        return $response->denied() ? $response->message() : 'Edit this product';
                    })
                    ->icon(function ($record) {
                        return Gate::inspect('update', $record)->denied() 
                            ? 'heroicon-o-lock-closed' 
                            : 'heroicon-o-pencil';
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
                ->authorize(fn () => true)
                ->visible(fn () => true)
                ->disabled(fn ($record) => Gate::denies('delete', $record))
                ->tooltip(function ($record) {
                    $response = Gate::inspect('delete', $record);
                    return $response->denied() ? $response->message() : 'Delete this product';
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
                        ->title('Action Denied')
                        ->body($response->message())
                        ->send();
                    
                    $action->halt();
                }
            }),
        ])
        ->toolbarActions([
            BulkActionGroup::make([
                DeleteBulkAction::make()
                    ->visible(fn () => Filament::auth()->user()?->is_super_admin)
                    ->before(function ($action) {
                        $currentUser = Filament::auth()->user();
                        
                        // jagaan layer 2, tpi umumnya tdk mungkin terjadi karena button sudah disembunyikan untuk non-superadmin
                        if (!$currentUser?->is_super_admin) {
                            Notification::make()
                                ->danger()
                                ->title('Access Denied')
                                ->body('Only Superadmin is allowed to delete product data.')
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
                            ->title('Products Deleted')
                            ->body("{$deletedCount} products successfully deleted from the system.")
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
