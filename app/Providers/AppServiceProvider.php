<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

use App\Models\Invoice;
use App\Models\Product;
use App\Observers\InvoiceObserver;
use App\Observers\ProductObserver;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Invoice::observe(InvoiceObserver::class);
        Product::observe(ProductObserver::class);

        \Filament\Support\Facades\FilamentView::registerRenderHook(
            \Filament\Tables\View\TablesRenderHook::TOOLBAR_START,
            fn (): string => view('filament.tables.stock-movement-export-button')->render(),
            scopes: \App\Filament\Resources\StockMovements\Pages\ListStockMovements::class,
        );
    }
}
