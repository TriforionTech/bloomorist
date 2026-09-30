<?php

namespace App\Filament\Resources\AccountingAudits\Pages;

use App\Filament\Resources\AccountingAudits\AccountingAuditResource;
use Filament\Resources\Pages\ListRecords;

class ListAccountingAudits extends ListRecords
{
    protected static string $resource = AccountingAuditResource::class;
}
