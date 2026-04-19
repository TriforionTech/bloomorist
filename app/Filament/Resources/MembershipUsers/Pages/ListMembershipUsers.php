<?php

namespace App\Filament\Resources\MembershipUsers\Pages;

use App\Filament\Resources\MembershipUsers\MembershipUserResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMembershipUsers extends ListRecords
{
    protected static string $resource = MembershipUserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
