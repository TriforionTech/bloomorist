<?php

namespace App\Filament\Resources\GeneralJournals;

use App\Filament\Resources\GeneralJournals\Pages\CreateGeneralJournal;
use App\Filament\Resources\GeneralJournals\Pages\ListGeneralJournals;
use App\Filament\Resources\GeneralJournals\Schemas\GeneralJournalForm;
use App\Filament\Resources\GeneralJournals\Tables\GeneralJournalsTable;
use App\Models\GeneralJournal;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

class GeneralJournalResource extends Resource
{
    protected static ?string $model = GeneralJournal::class;

    protected static ?string $navigationLabel = 'Jurnal Umum';
    protected static ?string $pluralLabel = 'Jurnal Umum';
    protected static ?string $label = 'Jurnal Umum';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static string|UnitEnum|null $navigationGroup = 'Accounting & Finances';
    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return GeneralJournalForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return GeneralJournalsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListGeneralJournals::route('/'),
            'create' => CreateGeneralJournal::route('/create'),
        ];
    }
}
