<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Gate;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    // public function mount(int | string $record): void
    // {
    //     if (!Filament::auth()->user()?->is_super_admin) {
    //         abort(403, 'FORBIDDEN');
    //     }

    //     parent::mount($record);
    // }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make()->visible(fn ($record) => 
                Filament::auth()->user()?->is_super_admin
            ),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
