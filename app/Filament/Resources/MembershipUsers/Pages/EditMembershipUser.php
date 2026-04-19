<?php

namespace App\Filament\Resources\MembershipUsers\Pages;

use App\Filament\Resources\MembershipUsers\MembershipUserResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditMembershipUser extends EditRecord
{
    protected static string $resource = MembershipUserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
