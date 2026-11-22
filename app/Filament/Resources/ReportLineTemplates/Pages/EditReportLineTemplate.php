<?php

namespace App\Filament\Resources\ReportLineTemplates\Pages;

use App\Filament\Resources\ReportLineTemplates\ReportLineTemplateResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditReportLineTemplate extends EditRecord
{
    protected static string $resource = ReportLineTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
