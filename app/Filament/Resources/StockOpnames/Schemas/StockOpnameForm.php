<?php

namespace App\Filament\Resources\StockOpnames\Schemas;

use App\Models\Product;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class StockOpnameForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Stock Opname Details')
                    ->columns(2)
                    ->schema([
                        TextInput::make('reference_number')
                            ->required()
                            ->maxLength(255)
                            ->default(fn () => 'SO-'.date('Y-m-d').'-'.Str::random(4))
                            ->readOnly(),
                        DatePicker::make('date')
                            ->required()
                            ->default(now()),
                        TextInput::make('notes')
                            ->columnSpanFull(),
                    ]),

                Section::make('Items')
                    ->schema([
                        Repeater::make('items')
                            ->relationship('items')
                            ->columns(6)
                            ->schema([
                                Select::make('product_id')
                                    ->label('Product')
                                    ->options(Product::where('is_active', true)->pluck('nama', 'id'))
                                    ->required()
                                    ->live()
                                    ->searchable()
                                    ->afterStateUpdated(function ($state, $set, $get) {
                                        if ($state) {
                                            $product = Product::find($state);
                                            if ($product) {
                                                $set('system_qty', $product->stok);
                                                $set('unit_cost', $product->harga_beli);

                                                $physical = (int) ($get('physical_qty') ?: 0);
                                                $variance = $physical - $product->stok;
                                                $set('variance', $variance);
                                                $set('total_value', $variance * $product->harga_beli);
                                            }
                                        }
                                    })
                                    ->columnSpan(2),
                                TextInput::make('system_qty')
                                    ->numeric()
                                    ->readOnly()
                                    ->dehydrated()
                                    ->required(),
                                TextInput::make('physical_qty')
                                    ->numeric()
                                    ->required()
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function ($state, $set, $get) {
                                        $system = (int) $get('system_qty');
                                        $physical = (int) $state;
                                        $variance = $physical - $system;
                                        $cost = (float) $get('unit_cost');

                                        $set('variance', $variance);
                                        $set('total_value', $variance * $cost);
                                    }),
                                TextInput::make('variance')
                                    ->numeric()
                                    ->readOnly()
                                    ->dehydrated(),
                                TextInput::make('unit_cost')
                                    ->numeric()
                                    ->readOnly()
                                    ->dehydrated()
                                    ->prefix('Rp'),
                                TextInput::make('total_value')
                                    ->numeric()
                                    ->readOnly()
                                    ->dehydrated()
                                    ->prefix('Rp'),
                            ])
                            ->defaultItems(0)
                            ->addActionLabel('Add Item'),
                    ]),
            ]);
    }
}
