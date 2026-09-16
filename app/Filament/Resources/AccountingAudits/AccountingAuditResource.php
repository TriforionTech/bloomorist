<?php

namespace App\Filament\Resources\AccountingAudits;

use App\Filament\Resources\AccountingAudits\Pages\ListAccountingAudits;
use App\Models\AccountingAudit;
use BackedEnum;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
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

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Textarea::make('old_values')->disabled(),
            Textarea::make('new_values')->disabled(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('WAKTU')->dateTime('d M Y H:i')->sortable(),
                TextColumn::make('user.name')->label('USER')->placeholder('System'),
                TextColumn::make('action')->label('AKSI')->badge(),
                TextColumn::make('journal.no_bukti')->label('NO. BUKTI')->searchable(),
                TextColumn::make('journal.keterangan')->label('KETERANGAN')->limit(40),
            ])
            ->filters([])
            ->recordActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAccountingAudits::route('/'),
        ];
    }
}
