<?php

namespace App\Filament\Resources\StockTransfers;

use App\Filament\Resources\StockTransfers\Pages\ManageStockTransfers;
use App\Filament\Resources\StockTransfers\Schemas\StockTransferForm;
use App\Filament\Resources\StockTransfers\Tables\StockTransfersTable;
use App\Models\StockTransfer;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class StockTransferResource extends Resource
{
    protected static ?string $model = StockTransfer::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static \UnitEnum|string|null $navigationGroup = 'PERSEDIAAN';

    protected static ?string $navigationLabel = 'Transfer Stok';

    protected static ?string $modelLabel = 'Transfer Stok';

    protected static ?string $pluralModelLabel = 'Transfer Stok';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return StockTransferForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return StockTransfersTable::configure($table);
    }

    public static function getWidgets(): array
    {
        return [
            Widgets\StockTransferOverview::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageStockTransfers::route('/'),
        ];
    }
}
