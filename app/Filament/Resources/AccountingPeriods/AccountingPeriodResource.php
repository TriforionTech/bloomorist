<?php

namespace App\Filament\Resources\AccountingPeriods;

use App\Filament\Resources\AccountingPeriods\Pages\CreateAccountingPeriod;
use App\Filament\Resources\AccountingPeriods\Pages\EditAccountingPeriod;
use App\Filament\Resources\AccountingPeriods\Pages\ListAccountingPeriods;
use App\Filament\Resources\AccountingPeriods\Schemas\AccountingPeriodForm;
use App\Filament\Resources\AccountingPeriods\Tables\AccountingPeriodsTable;
use App\Models\AccountingPeriod;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

class AccountingPeriodResource extends Resource
{
    protected static ?string $model = AccountingPeriod::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    protected static string|UnitEnum|null $navigationGroup = 'AKUNTANSI';

    protected static ?string $navigationLabel = 'Periode Akuntansi';

    protected static ?string $modelLabel = 'Periode Akuntansi';

    protected static ?string $pluralModelLabel = 'Periode Akuntansi';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return AccountingPeriodForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AccountingPeriodsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAccountingPeriods::route('/'),
            'create' => CreateAccountingPeriod::route('/create'),
            'edit' => EditAccountingPeriod::route('/{record}/edit'),
        ];
    }
}
