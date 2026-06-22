<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use App\Models\Product;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->visible(fn () => Filament::auth()->user()?->is_super_admin),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All')
                ->icon('heroicon-o-squares-2x2')
                ->badge(fn () => Product::where('is_active', true)->count()),

            'bunga' => Tab::make('Flowers')
                ->icon('heroicon-o-sparkles')
                ->badge(fn () => Product::where('is_active', true)->where('kategori', 'bunga')->count())
                ->modifyQueryUsing(fn (Builder $query) => $query->where('kategori', 'bunga')),

            'packaging' => Tab::make('Packaging')
                ->icon('heroicon-o-archive-box')
                ->badge(fn () => Product::where('is_active', true)->where('kategori', 'packaging')->count())
                ->modifyQueryUsing(fn (Builder $query) => $query->where('kategori', 'packaging')),

            'others' => Tab::make('Others')
                ->icon('heroicon-o-tag')
                ->badge(fn () => Product::where('is_active', true)->where('kategori', 'others')->count())
                ->modifyQueryUsing(fn (Builder $query) => $query->where('kategori', 'others')),
        ];
    }
}
