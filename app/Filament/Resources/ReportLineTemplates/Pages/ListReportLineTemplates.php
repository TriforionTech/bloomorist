<?php

namespace App\Filament\Resources\ReportLineTemplates\Pages;

use App\Filament\Resources\ReportLineTemplates\ReportLineTemplateResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListReportLineTemplates extends ListRecords
{
    protected static string $resource = ReportLineTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
