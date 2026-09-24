<?php

namespace App\Filament\Resources\AccountingAudits;

use App\Filament\Resources\AccountingAudits\Pages\ListAccountingAudits;
use App\Filament\Resources\AccountingAudits\Schemas\AccountingAuditInfolist;
use App\Filament\Resources\AccountingAudits\Tables\AccountingAuditsTable;
use App\Models\AccountingAudit;
use BackedEnum;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class AccountingAuditResource extends Resource
{
    protected static ?string $model = AccountingAudit::class;

    protected static ?string $navigationLabel = 'Accounting Audit Trail';

    protected static ?string $modelLabel = 'Accounting Audit';

    protected static ?string $pluralModelLabel = 'Accounting Audits';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';

    protected static string|UnitEnum|null $navigationGroup = 'Accounting & Finances';

    protected static ?int $navigationSort = 8;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Textarea::make('old_values')->disabled(),
            Textarea::make('new_values')->disabled(),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return AccountingAuditInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AccountingAuditsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAccountingAudits::route('/'),
        ];
    }
}
