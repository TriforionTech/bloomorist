<?php

namespace App\Filament\Resources\ReportLineTemplates;

use App\Filament\Resources\ReportLineTemplates\Pages\CreateReportLineTemplate;
use App\Filament\Resources\ReportLineTemplates\Pages\EditReportLineTemplate;
use App\Filament\Resources\ReportLineTemplates\Pages\ListReportLineTemplates;
use App\Filament\Resources\ReportLineTemplates\Schemas\ReportLineTemplateForm;
use App\Filament\Resources\ReportLineTemplates\Tables\ReportLineTemplatesTable;
use App\Models\ReportLineTemplate;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

class ReportLineTemplateResource extends Resource
{
    protected static ?string $model = ReportLineTemplate::class;
    protected static ?string $navigationLabel = 'Report Templates';
    protected static ?string $modelLabel = 'Report Template';
    protected static ?string $pluralModelLabel = 'Report Templates';
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-chart-bar';
    protected static string|UnitEnum|null $navigationGroup = 'Controls & Audit';
    protected static ?int $navigationSort = 7;
    protected static bool $shouldRegisterNavigation = false;

    public static function form(Schema $schema): Schema
    {
        return ReportLineTemplateForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ReportLineTemplatesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListReportLineTemplates::route('/'),
            'create' => CreateReportLineTemplate::route('/create'),
            'edit' => EditReportLineTemplate::route('/{record}/edit'),
        ];
    }
}
