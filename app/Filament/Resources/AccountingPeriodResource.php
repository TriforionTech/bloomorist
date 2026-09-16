<?php

namespace App\Filament\Resources;

use App\Models\AccountingPeriod;
use App\Filament\Resources\AccountingPeriods\Schemas\AccountingPeriodForm;
use App\Filament\Resources\AccountingPeriods\Tables\AccountingPeriodsTable;
use BackedEnum;
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Schemas\Schema;

class AccountingPeriodResource extends Resource
{
    protected static ?string $model = AccountingPeriod::class;
    
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';
    
    protected static string|UnitEnum|null $navigationGroup = 'Accounting & Finances';
    
    protected static ?string $modelLabel = 'Accounting Period';
    protected static ?string $pluralModelLabel = 'Accounting Periods';
    
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
            'index' => \App\Filament\Resources\AccountingPeriods\Pages\ListAccountingPeriods::route('/'),
            'create' => \App\Filament\Resources\AccountingPeriods\Pages\CreateAccountingPeriod::route('/create'),
            'edit' => \App\Filament\Resources\AccountingPeriods\Pages\EditAccountingPeriod::route('/{record}/edit'),
        ];
    }
}
