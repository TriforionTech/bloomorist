<?php

namespace App\Filament\Resources\Expenses\Pages;

use App\Filament\Resources\Expenses\ExpenseResource;
use App\Models\Expense;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListExpenses extends ListRecords
{
    protected static string $resource = ExpenseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Tambah Pengeluaran'),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Semua')
                ->badge(fn () => Expense::count()),

            'draft' => Tab::make('Draft')
                ->icon('heroicon-o-pencil-square')
                ->badge(fn () => Expense::where('status', 'draft')->count())
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'draft')),

            'posted' => Tab::make('Diposting')
                ->icon('heroicon-o-check-circle')
                ->badge(fn () => Expense::where('status', 'posted')->count())
                ->badgeColor('success')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'posted')),

            'void' => Tab::make('Dibatalkan (Void)')
                ->icon('heroicon-o-x-circle')
                ->badge(fn () => Expense::where('status', 'void')->count())
                ->badgeColor('danger')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'void')),
        ];
    }
}
