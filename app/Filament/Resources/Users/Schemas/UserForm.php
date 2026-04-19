<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->placeholder('Enter admin name')
                    ->required(),
                TextInput::make('email')
                    ->label('Email address')
                    ->email()
                    ->placeholder('example@domain.com')
                    ->required(),
                TextInput::make('password')
                    ->password()
                    ->placeholder('Enter secure password')
                    ->required(),
                TextInput::make('confirm-password')
                    ->password()
                    ->placeholder('Confirm your password')
                    ->required()
                    ->same('password')
                    ->validationMessages([
                        'same' => 'Password confirmation does not match.',
                    ]),
                Select::make('is_super_admin')
                    ->label('Super Admin')
                    ->placeholder('Select role')
                    ->options([
                        0 => 'No',
                        1 => 'Yes',
                    ])
                    ->visible(fn ($record) =>
                        Filament::auth()->user()?->is_super_admin &&
                        Filament::auth()->id() !== $record?->id
                    )
                    ->default(0)
                    ->required(),
            ]);
    }
}
