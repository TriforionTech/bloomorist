<?php

namespace App\Filament\Resources\MembershipUsers\Pages;

use App\Filament\Resources\MembershipUsers\MembershipUserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateMembershipUser extends CreateRecord
{
    protected static string $resource = MembershipUserResource::class;

    protected function getRedirectUrl(): string
    {
        // arahkan ke halaman list/index tabel setelah berhasil create
        return $this->getResource()::getUrl('index'); 
    }
}
